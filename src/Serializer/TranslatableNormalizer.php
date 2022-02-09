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

    public function normalize($object, string $format = null, array $context = [])
    {
        $translations = [];
        $data = $this->normalizer->normalize($object, $format, $context);
        $repository = $this->manager->getRepository('Gedmo\Translatable\Entity\Translation');

        foreach ($repository->findTranslations($object) as $locale => $translation) {
            foreach ($translation as $field => $value) {
                $translations[$field] = ($translations[$field] ?? []);
                $translations[$field][strtoupper($locale)] = $value;
            }
        }

        $data['translations'] = $translations;
        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof Translatable && ($context['with_translations'] ?? false);
    }
}
