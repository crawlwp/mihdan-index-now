<?php

/**
 * AttributeTypeAndValue
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace Mihdan\IndexNow\Dependencies\phpseclib3\File\ASN1\Maps;

use Mihdan\IndexNow\Dependencies\phpseclib3\File\ASN1;
/**
 * AttributeTypeAndValue
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class AttributeTypeAndValue
{
    const MAP = ['type' => ASN1::TYPE_SEQUENCE, 'children' => ['type' => AttributeType::MAP, 'value' => AttributeValue::MAP]];
}
