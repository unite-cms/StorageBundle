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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UnitedCMS\CoreBundle\Entity\ContentType;
use UnitedCMS\CoreBundle\Entity\SettingType;
use UnitedCMS\StorageBundle\Form\SignInputType;
use UnitedCMS\StorageBundle\Service\StorageService;

class SignController extends Controller {


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

    // TODO: This should only be possible when auth is TOKEN and not Cookie
    $form = $this->createForm(SignInputType::class, null, ['csrf_protection' => false]);
    $form->handleRequest($request);

    if($form->isSubmitted() && $form->isValid()) {
      try {
        return new Response(StorageService::createPreSignedUploadUrlForFieldPath(
          $form->getData()['filename'],
          $contentType,
          $form->getData()['field']
        ));
      } catch (\InvalidArgumentException $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    throw new BadRequestHttpException($form->getErrors(true, true));
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

    // TODO: This should only be possible when auth is TOKEN and not Cookie
    $form = $this->createForm(SignInputType::class, null, ['csrf_protection' => false]);
    $form->handleRequest($request);

    if($form->isSubmitted() && $form->isValid()) {
      try {
        return new Response(StorageService::createPreSignedUploadUrlForFieldPath(
          $form->getData()['filename'],
          $settingType,
          $form->getData()['field']
        ));
      } catch (\InvalidArgumentException $e) {
        throw new BadRequestHttpException($e->getMessage());
      }
    }

    throw new BadRequestHttpException($form->getErrors(true, true));
  }
}