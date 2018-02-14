<?php

namespace UnitedCMS\StorageBundle\Field\Types;

use Symfony\Component\Routing\Router;
use Symfony\Component\Validator\ConstraintViolation;
use UnitedCMS\CoreBundle\Entity\ContentType;
use UnitedCMS\CoreBundle\Entity\ContentTypeField;
use UnitedCMS\CoreBundle\Entity\Fieldable;
use UnitedCMS\CoreBundle\Entity\FieldableField;
use UnitedCMS\CoreBundle\Entity\NestableFieldable;
use UnitedCMS\CoreBundle\Entity\SettingType;
use UnitedCMS\CoreBundle\Entity\SettingTypeField;
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

    private function getRootEntity(Fieldable $fieldable) {
        if($fieldable instanceof NestableFieldable) {
            if($fieldable->getParentEntity()) {
                return $this->getRootEntity($fieldable->getParentEntity());
            }
        }
        return $fieldable;
    }

    private function getFieldPathPrefix(Fieldable $fieldable) {
        $path = '';
        if($fieldable instanceof NestableFieldable) {
            $path = $this->getFieldPathPrefix($fieldable->getParentEntity()) . $fieldable->getIdentifier() . '/';
        }
        return $path;
    }

    function getFormOptions(): array
    {
        $url = null;

        // To generate the sing url we need to find out the base fieldable.
        $fieldable = $this->getRootEntity($this->field->getEntity());

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

        return array_merge(parent::getFormOptions(), [
          'attr' => [
            'file-types' => $this->field->getSettings()->file_types,
            'field-path' => $this->getFieldPathPrefix($this->field->getEntity()) . $this->field->getIdentifier(),
            'endpoint' => $this->field->getSettings()->bucket['endpoint'] . '/' . $this->field->getSettings()->bucket['bucket'],
            'upload-sign-url' => $url
          ],
        ]);
    }

    function getGraphQLType(SchemaTypeManager $schemaTypeManager, $nestingLevel = 0) {
        return $schemaTypeManager->getSchemaType('StorageFile');
    }

    function getGraphQLInputType(SchemaTypeManager $schemaTypeManager, $nestingLevel = 0) {
        return $schemaTypeManager->getSchemaType('StorageFileInput');
    }

    function resolveGraphQLData($value)
    {
        if (!$this->fieldIsPresent()) {
            return 'undefined';
        }

        // Create full URL to file.
        $value['url'] = $this->field->getSettings()->bucket['endpoint'] . '/' . $this->field->getSettings()->bucket['bucket'] . '/' . $value['id'] . '/' . $value['name'];
        return $value;
    }

    function validateData($data): array
    {
        $violations = [];

        if(empty($data)) {
            return $violations;
        }

        if(empty($data['size']) || empty($data['id']) || empty($data['name']) || empty($data['checksum'])) {
            $violations[] = new ConstraintViolation(
              'validation.missing_definition',
              'validation.missing_definition',
              [],
              null,
              '[' . $this->getIdentifier() . ']',
              $data
            );
        }

        if(empty($violations)) {
            $preSignedUrl = new PreSignedUrl('', $data['id'], $data['name'], $data['checksum']);
            if (!$preSignedUrl->check($this->secret)) {
                $violations[] = new ConstraintViolation(
                  'validation.invalid_checksum',
                  'validation.invalid_checksum',
                  [],
                  null,
                  '['.$this->getIdentifier().']',
                  $data
                );
            }
        }

        return $violations;
    }

    function validateSettings(FieldableFieldSettings $settings): array
    {
        // Validate allowed and required settings.
        $violations = parent::validateSettings($settings);

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