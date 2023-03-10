<?php

namespace Experteam\ApiRedisBundle\Serializer;

use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Translatable;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class TranslatableNormalizer implements ContextAwareNormalizerInterface
{
    /**
     * @var ObjectNormalizer
     */
    private $normalizer;

    /**
     * @var EntityManagerInterface
     */
    private $manager;

    public function __construct(ObjectNormalizer $normalizer, EntityManagerInterface $manager)
    {
        $this->normalizer = $normalizer;
        $this->manager = $manager;
    }

    /**
     * @param $object
     * @param string|null $format
     * @param array $context
     * @return mixed
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        $config = [];
        if (method_exists($object, 'getTranslations')) {
            foreach ($object->getTranslations() as $translation) {
                $config[] = [$translation->getField(), $translation->getLocale(), $translation->getContent()];
            }
        } else {
            $repository = $this->manager->getRepository('Gedmo\Translatable\Entity\Translation');
            foreach ($repository->findTranslations($object) as $locale => $translation) {
                foreach ($translation as $field => $value) {
                    $config[] = [$field, $locale, $value];
                }
            }
        }

        $translations = [];
        foreach ($config as [$field, $locale, $value]) {
            $translations[$field] = ($translations[$field] ?? []);
            $translations[$field][strtoupper($locale)] = $value;
        }

        $data['translations'] = $translations;
        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof Translatable && ($context['with_translations'] ?? false);
    }
}
