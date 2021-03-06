<?php

declare(strict_types=1);

namespace Akeneo\Platform\Component\EventQueue;

use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @copyright 202O Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BusinessEventNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof BusinessEvent;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return is_subclass_of($type, BusinessEvent::class);
    }

    /**
     * @param BusinessEvent $object
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (false === $this->supportsNormalization($object, $format)) {
            throw new \InvalidArgumentException();
        }

        return [
            'name' => $object->name(),
            'author' => $object->author(),
            'data' => $object->data(),
            'timestamp' => $object->timestamp(),
            'uuid' => $object->uuid()
        ];
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        if (false === $this->supportsDenormalization($data, $type, $format)) {
            throw new \InvalidArgumentException();
        }

        if (!class_exists($type)) {
            throw new RuntimeException(sprintf('The class "%s" is not defined.', $type));
        }

        return new $type(
            $data['author'],
            $data['data'],
            $data['timestamp'],
            $data['uuid']
        );
    }
}
