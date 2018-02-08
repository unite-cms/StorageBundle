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
use Symfony\Component\HttpFoundation\Response;
use UnitedCMS\CoreBundle\Entity\ContentType;
use UnitedCMS\CoreBundle\Entity\SettingType;

class SignController extends Controller {

  /**
   * @Route("/content/{content_type}/upload")
   * @Method({"POST"})
   * @Entity("contentType", expr="repository.findByIdentifiers(organization, domain, content_type)")
   * @Security("is_granted(constant('UnitedCMS\\CoreBundle\\Security\\ContentVoter::CREATE'), contentType)")
   *
   * @param ContentType $contentType
   * @return Response
   */
  public function uploadContentTypeAction(ContentType $contentType) {

  }

  /**
   * @Route("/setting/{setting_type}/upload")
   * @Method({"POST"})
   * @Entity("settingType", expr="repository.findByIdentifiers(organization, domain, setting_type)")
   * @Security("is_granted(constant('UnitedCMS\\CoreBundle\\Security\\SettingVoter::UPDATE'), settingType)")
   *
   * @param SettingType $settingType
   * @return Response
   */
  public function uploadSettingTypeAction(SettingType $settingType) {

  }


}