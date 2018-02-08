<?php
/**
 * Created by PhpStorm.
 * User: franzwilding
 * Date: 08.02.18
 * Time: 09:31
 */

namespace UnitedCMS\StorageBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use UnitedCMS\CoreBundle\Entity\ContentType;
use UnitedCMS\CoreBundle\Entity\ContentTypeField;
use UnitedCMS\CoreBundle\Entity\Fieldable;
use UnitedCMS\CoreBundle\Entity\FieldableField;
use UnitedCMS\CoreBundle\Entity\SettingType;
use UnitedCMS\CoreBundle\Entity\SettingTypeField;
use UnitedCMS\CoreBundle\Field\FieldableFieldSettings;
use UnitedCMS\CoreBundle\Field\Types\TextFieldType;
use UnitedCMS\StorageBundle\Field\Types\FileFieldType;

class SignController extends Controller {

  /**
   * @param Fieldable $fieldable
   * @param $path
   *
   * @return null|FieldableField
   */
  private function resolveFieldPath(Fieldable $fieldable, $path) {
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
      if($field instanceof ContentTypeField && !empty($field->getSettings()['fields'][$root])) {
        $collection = new ContentType();
        $nestedField = new ContentTypeField();
        $nestedField
          ->setTitle($field->getSettings()['fields'][$root]['title'])
          ->setIdentifier($field->getSettings()['fields'][$root]['identifier'])
          ->setType($field->getSettings()['fields'][$root]['type'])
          ->setSettings(new FieldableFieldSettings($field->getSettings()['fields'][$root]['settings']));
        $collection->addField($nestedField);
        return $this->resolveFieldPath($collection, join('/', $parts));
      }

      if($field instanceof SettingTypeField && !empty($field->getSettings()['fields'][$root])) {
        $collection = new SettingType();
        $nestedField = new SettingTypeField();
        $nestedField
          ->setTitle($field->getSettings()['fields'][$root]['title'])
          ->setIdentifier($field->getSettings()['fields'][$root]['identifier'])
          ->setType($field->getSettings()['fields'][$root]['type'])
          ->setSettings(new FieldableFieldSettings($field->getSettings()['fields'][$root]['settings']));
        $collection->addField($nestedField);
        return $this->resolveFieldPath($collection, join('/', $parts));
      }
    }

    return null;
  }

  /**
   * @Route("/content/{content_type}/upload")
   * @Method({"POST"})
   * @Entity("contentType", expr="repository.findByIdentifiers(organization, domain, content_type)")
   * @Security("is_granted(constant('UnitedCMS\\CoreBundle\\Security\\ContentVoter::CREATE'), contentType)")
   *
   * @param ContentType $contentType
   * @param Request $request
   *
   * @return Response
   */
  public function uploadContentTypeAction(ContentType $contentType, Request $request) {

    $form = $this->createFormBuilder()
      ->add('field', TextType::class, ['required' => true])
      ->add('filename', TextType::class, ['required' => true])
      ->getForm();
    $form->handleRequest($request);

    if($form->isSubmitted() && $form->isValid()) {

      // Resolve field in contentType.
      if(!$field = $this->resolveFieldPath($contentType, $form->getData()['field'])) {
        return new Response('Field not found in content type.', 400);
      }

      // Return pre-signed url
      // TODO
    }

    return new Response($form->getErrors(true, true), 400);
  }

  /**
   * @Route("/setting/{setting_type}/upload")
   * @Method({"POST"})
   * @Entity("settingType", expr="repository.findByIdentifiers(organization, domain, setting_type)")
   * @Security("is_granted(constant('UnitedCMS\\CoreBundle\\Security\\SettingVoter::UPDATE'), settingType)")
   *
   * @param SettingType $settingType
   * @param Request $request
   *
   * @return Response
   */
  public function uploadSettingTypeAction(SettingType $settingType, Request $request) {

    $form = $this->createFormBuilder()
      ->add('field', TextType::class, ['required' => true])
      ->add('filename', TextType::class, ['required' => true])
      ->getForm();
    $form->handleRequest($request);

    if($form->isSubmitted() && $form->isValid()) {

      // Resolve field in contentType.
      if(!$field = $this->resolveFieldPath($settingType, $form->getData()['field'])) {
        return new Response('Field not found in setting type.', 400);
      }

      // Return pre-signed url
      // TODO
    }

    return new Response($form->getErrors(true, true), 400);
  }


}