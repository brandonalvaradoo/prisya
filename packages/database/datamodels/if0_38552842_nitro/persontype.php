<?php

class PersonType extends Database\Datamodel
{
    protected $name;
    protected $description;
    protected $price;
    protected $is_vip;

    public function GetName(): string
    {
        return $this->name;
    }

    public function GetDescription(): string
    {
        return $this->description;
    }

    public function SetName(string $name): void
    {
        $this->name = $name;
    }
    
    public function SetDescription(string $description): void
    {
        $this->description = $description;
    }

    public function SetPrice(float $price): void
    {
        $this->price = $price;
    }

    public function GetPrice(): float
    {
        return $this->price;
    }
    
    public function IsVip(): bool
    {
        return $this->is_vip;
    }
}