<?php
/**
 * Created by PhpStorm.
 * User: franzwilding
 * Date: 09.02.18
 * Time: 09:35
 */

namespace UnitedCMS\StorageBundle\Service;

use Aws\S3\S3Client;
use Ramsey\Uuid\Uuid;
use UnitedCMS\CoreBundle\Entity\ContentType;
use UnitedCMS\CoreBundle\Entity\ContentTypeField;
use UnitedCMS\CoreBundle\Entity\Fieldable;
use UnitedCMS\CoreBundle\Entity\FieldableField;
use UnitedCMS\CoreBundle\Entity\SettingType;
use UnitedCMS\CoreBundle\Entity\SettingTypeField;
use UnitedCMS\CoreBundle\Field\FieldableFieldSettings;
use UnitedCMS\StorageBundle\Field\Types\FileFieldType;
use UnitedCMS\StorageBundle\Model\PreSignedUrl;

class StorageService {

  /**
   * @param Fieldable $fieldable
   * @param $path
   *
   * @return null|FieldableField
   */
  public static function resolveFileFieldPath(Fieldable $fieldable, $path) {
    $parts = explode('/', $path);

    if(!$root = array_shift($parts)) {
      return null;
    }

    /**
     * @var FieldableField $field
     */
    if(!$field = $fieldable->getFields()->get($root)) {
      return null;
    }

    // If we have reached the end of the nested field path, return the field.
    if(empty($parts)) {
      if($field->getType() == FileFieldType::TYPE) {
        return $field;
      }
    }

    // If this part is a collection field type.
    else {
      if($field instanceof ContentTypeField && property_exists($field->getSettings(), 'fields') && !empty($field->getSettings()->fields[$root])) {
        $collection = new ContentType();
        $nestedField = new ContentTypeField();
        $nestedField
          ->setTitle($field->getSettings()->fields[$root]['title'])
          ->setIdentifier($field->getSettings()->fields[$root]['identifier'])
          ->setType($field->getSettings()->fields[$root]['type'])
          ->setSettings(new FieldableFieldSettings($field->getSettings()->fields[$root]['settings']));
        $collection->addField($nestedField);
        return self::resolveFileFieldPath($collection, join('/', $parts));
      }

      if($field instanceof SettingTypeField && property_exists($field->getSettings(), 'fields') && !empty($field->getSettings()->fields[$root])) {
        $collection = new SettingType();
        $nestedField = new SettingTypeField();
        $nestedField
          ->setTitle($field->getSettings()->fields[$root]['title'])
          ->setIdentifier($field->getSettings()->fields[$root]['identifier'])
          ->setType($field->getSettings()->fields[$root]['type'])
          ->setSettings(new FieldableFieldSettings($field->getSettings()->fields[$root]['settings']));
        $collection->addField($nestedField);
        return self::resolveFileFieldPath($collection, join('/', $parts));
      }
    }

    return null;
  }

  /**
   * Pre-Signs an upload action for the given filename and field configuration.
   *
   * @param string $filename
   * @param array $bucket_settings
   * @param string $allowed_file_types
   *
   * @return PreSignedUrl
   */
  public static function createPreSignedUploadUrl(string $filename, array $bucket_settings, string $allowed_file_types = '*') {

    // Check if file type is allowed.
    $filenameparts = explode('.', $filename);
    if(count($filenameparts) < 2) {
      throw new \InvalidArgumentException('Filename must include a file type extension.');
    }

    $filenameextension = array_pop($filenameparts);

    $filenameextension_supported = false;

    foreach(explode(',', str_replace(' ', '', $allowed_file_types)) as $extension) {
      if($extension === '*') {
        $filenameextension_supported = true;
      }
      if($filenameextension === strtolower($extension)) {
        $filenameextension_supported = true;
      }
    }

    if(!$filenameextension_supported) {
      throw new \InvalidArgumentException('File type "' . $filenameextension . '" not supported');
    }

    $uuid = (string) Uuid::uuid1();

    // Return pre-signed url
    $s3Client = new S3Client([
      'version' => 'latest',
      'region'  => 'us-east-1',
      'endpoint' => $bucket_settings['endpoint'],
      'use_path_style_endpoint' => true,
      'credentials' => [
        'key'    => $bucket_settings['key'],
        'secret' => $bucket_settings['secret'],
      ],
    ]);

    $command = $s3Client->getCommand('PutObject', [
      'Bucket' => $bucket_settings['bucket'],
      'Key'    => $uuid . '/' . $filename,
    ]);

    return new PreSignedUrl(
      (string) $s3Client->createPresignedRequest($command, '+5 minutes')->getUri(),
      $uuid,
      $filename
    );
  }

  /**
   * Wrapper around createPreSignedUploadUrl to get settings from field settings.
   *
   * @param string $filename
   * @param Fieldable $fieldable
   * @param string $field_path
   *
   * @return PreSignedUrl
   */
  public static function createPreSignedUploadUrlForFieldPath(string $filename, Fieldable $fieldable, string $field_path) {

    if(!$field = self::resolveFileFieldPath($fieldable, $field_path)) {
      throw new \InvalidArgumentException('Field "' . $field_path . '" not found in fieldable.');
    }

    // Check if config is available.
    if(!property_exists($field->getSettings(), 'bucket') || empty($field->getSettings()->bucket)) {
      throw new \InvalidArgumentException('Invalid field definition.');
    }

    // Check if config is available.
    $allowed_field_types = '*';
    if(property_exists($field->getSettings(), 'file_types') && !empty($field->getSettings()->file_types)) {
      $allowed_field_types = $field->getSettings()->file_types;
    }

    return self::createPreSignedUploadUrl($filename, $field->getSettings()->bucket, $allowed_field_types);
  }
}