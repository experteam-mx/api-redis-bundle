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
     * @var TranslationRepository
     */
    private $repository;

    public function __construct(ObjectNormalizer $normalizer, EntityManagerInterface $manager)
    {
        $this->normalizer = $normalizer;
        $this->repository = $manager->getRepository('Gedmo\Translatable\Entity\Translation');
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        $translations = [];
        foreach ($this->repository->findTranslations($object) as $locale => $translation)
            foreach ($translation as $field => $value) {
                $translations[$field] = $translations[$field] ?? [];
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