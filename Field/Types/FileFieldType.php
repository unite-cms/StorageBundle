<?php

namespace UnitedCMS\StorageBundle\Field\Types;

use Symfony\Component\Routing\Router;
use Symfony\Component\Validator\ConstraintViolation;
use UnitedCMS\CoreBundle\Entity\ContentType;
use UnitedCMS\CoreBundle\Entity\FieldableField;
use UnitedCMS\CoreBundle\Entity\SettingType;
use UnitedCMS\CoreBundle\Field\FieldableFieldSettings;
use UnitedCMS\CoreBundle\Field\FieldType;
use UnitedCMS\CoreBundle\SchemaType\SchemaTypeManager;
use UnitedCMS\StorageBundle\Form\StorageFileType;
use UnitedCMS\StorageBundle\Model\PreSignedUrl;

class FileFieldType extends FieldType
{
    const TYPE                      = "file";
    const FORM_TYPE                 = StorageFileType::class;
    const SETTINGS                  = ['file_types', 'bucket'];
    const REQUIRED_SETTINGS         = ['bucket'];

    private $router;
    private $secret;

    public function __construct(Router $router, string $secret)
    {
        $this->router = $router;
        $this->secret = $secret;
    }

    function getFormOptions(FieldableField $field): array
    {
        $url = null;

        // To generate the sing url we need to find out the base fieldable.
        $fieldable = $field->getEntity()->getRootEntity();

        if($fieldable instanceof ContentType) {
            $url = $this->router->generate('unitedcms_storage_sign_uploadcontenttype', [
              'organization' => $fieldable->getDomain()->getOrganization()->getIdentifier(),
              'domain' => $fieldable->getDomain()->getIdentifier(),
              'content_type' => $fieldable->getIdentifier(),
            ], Router::ABSOLUTE_URL);
        }

        else if($fieldable instanceof SettingType) {
            $url = $this->router->generate('unitedcms_storage_sign_uploadsettingtype', [
              'organization' => $fieldable->getDomain()->getOrganization()->getIdentifier(),
              'domain' => $fieldable->getDomain()->getIdentifier(),
              'content_type' => $fieldable->getIdentifier(),
            ], Router::ABSOLUTE_URL);
        }

        // Use the identifier path part, but exclude root entity and include field identifier.
        $identifier_path_parts = explode('/', $field->getEntity()->getIdentifierPath());
        array_shift($identifier_path_parts);
        $identifier_path_parts[] = $field->getIdentifier();

        return array_merge(parent::getFormOptions($field), [
          'attr' => [
            'file-types' => $field->getSettings()->file_types,
            'field-path' => join('/', $identifier_path_parts),
            'endpoint' => $field->getSettings()->bucket['endpoint'] . '/' . $field->getSettings()->bucket['bucket'],
            'upload-sign-url' => $url
          ],
        ]);
    }

    function getGraphQLType(FieldableField $field, SchemaTypeManager $schemaTypeManager, $nestingLevel = 0) {
        return $schemaTypeManager->getSchemaType('StorageFile');
    }

    function getGraphQLInputType(FieldableField $field, SchemaTypeManager $schemaTypeManager, $nestingLevel = 0) {
        return $schemaTypeManager->getSchemaType('StorageFileInput');
    }

    function resolveGraphQLData(FieldableField $field, $value)
    {
        // Create full URL to file.
        $value['url'] = $field->getSettings()->bucket['endpoint'] . '/' . $field->getSettings()->bucket['bucket'] . '/' . $value['id'] . '/' . $value['name'];
        return $value;
    }

    function validateData(FieldableField $field, $data): array
    {
        $violations = [];

        if(empty($data)) {
            return $violations;
        }

        if(empty($data['size']) || empty($data['id']) || empty($data['name']) || empty($data['checksum'])) {
            $violations[] = $this->createViolation($field, 'validation.missing_definition');
        }

        if(empty($violations)) {
            $preSignedUrl = new PreSignedUrl('', $data['id'], $data['name'], $data['checksum']);
            if (!$preSignedUrl->check($this->secret)) {
                $violations[] = $this->createViolation($field, 'validation.invalid_checksum');
            }
        }

        return $violations;
    }

    function validateSettings(FieldableField $field, FieldableFieldSettings $settings): array
    {
        // Validate allowed and required settings.
        $violations = parent::validateSettings($field, $settings);

        // Validate bucket configuration.
        if(empty($violations)) {
            foreach(['endpoint', 'key', 'secret', 'bucket'] as $required_field) {
                if(!isset($settings->bucket[$required_field])) {
                    $violations[] = new ConstraintViolation(
                      'validation.required',
                      'validation.required',
                      [],
                      $settings->bucket,
                      'bucket.' . $required_field,
                      $settings->bucket
                    );
                }
            }
        }

        if(empty($violations)) {
            if(!preg_match("/^(http|https):\/\//", $settings->bucket['endpoint'])) {
                $violations[] = new ConstraintViolation(
                  'validation.absolute_url',
                  'validation.absolute_url',
                  [],
                  $settings->bucket,
                  'bucket.endpoint',
                  $settings->bucket
                );
            }
        }

        return $violations;
    }
}