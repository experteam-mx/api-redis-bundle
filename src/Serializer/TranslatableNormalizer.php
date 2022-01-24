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

        $data['translations'] = $this->repository->findTranslations($object);

        return $data;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof Translatable && ($context['with_translations'] ?? false);
    }
}