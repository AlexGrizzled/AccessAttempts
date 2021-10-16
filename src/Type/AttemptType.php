<?php

namespace AlexGrizzled\Type;

class AttemptType
{
    protected ?int $start;
    protected ?int $counter;

    public static function create(): self
    {
        return (new self)
            ->setStart(time())
            ->setCounter(0)
        ;
    }

    public function getStart(): ?int
    {
        return $this->start;
    }

    public function setStart(int $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function getCounter(): ?int
    {
        return $this->counter;
    }

    public function setCounter(int $counter): self
    {
        $this->counter = $counter;

        return $this;
    }

    public function inc(): self
    {
        $this->counter++;

        return $this;
    }
}
