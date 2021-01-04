<?php

namespace Experteam\ApiRedisBundle\Entity;

use Experteam\ApiRedisBundle\Repository\EntityWithPostChangeRepository;
*
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**@ORM\Entity(repositoryClass=EntityWithPostChangeRepository::class)
 */
class EntityWithPostChange
{
    use TimestampableEntity;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="bigint")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50, unique=true)
     */
    private $class;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $prefix;

    /**
     * @ORM\Column(type="string", length=50, options={"default": "getId"})
     */
    private $method = 'getId';

    /**
     * @ORM\Column(type="boolean")
     */
    private $toRedis;

    /**
     * @ORM\Column(type="boolean")
     */
    private $dispatchMessage;

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    private $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getToRedis(): ?bool
    {
        return $this->toRedis;
    }

    public function setToRedis(bool $toRedis): self
    {
        $this->toRedis = $toRedis;

        return $this;
    }

    public function getDispatchMessage(): ?bool
    {
        return $this->dispatchMessage;
    }

    public function setDispatchMessage(bool $dispatchMessage): self
    {
        $this->dispatchMessage = $dispatchMessage;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }
}