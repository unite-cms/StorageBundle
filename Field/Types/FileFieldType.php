<?php

namespace UnitedCMS\StorageBundle\Field\Types;

use UnitedCMS\CoreBundle\Field\FieldType;
use UnitedCMS\CoreBundle\Form\WebComponentType;

class FileFieldType extends FieldType
{
    const TYPE                      = "file";
    const FORM_TYPE                 = WebComponentType::class;
    const SETTINGS                  = ['file_types', 'bucket'];
    const REQUIRED_SETTINGS         = ['bucket'];
}