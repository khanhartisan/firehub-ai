<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\Serializable;

class Human implements Serializable
{
    use \App\Concerns\Serializable;

    protected Role $role = Role::UNKNOWN;

    protected ?string $email = null;

    protected ?string $name = null;

    protected ?string $description = null;

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'role' => $this->getRole()->value,
            'email' => $this->getEmail(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (! array_key_exists('role', $data)) {
            throw new \InvalidArgumentException('Human requires a "role".');
        }

        $rawRole = $data['role'];
        $role = $rawRole instanceof Role
            ? $rawRole
            : (is_string($rawRole) ? Role::tryFrom($rawRole) : null);

        if (! $role instanceof Role) {
            throw new \InvalidArgumentException('Human "role" is invalid.');
        }

        $human = (new static)->setRole($role);

        if (array_key_exists('email', $data)) {
            $human->setEmail($data['email'] !== null ? (string) $data['email'] : null);
        }

        if (array_key_exists('name', $data)) {
            $human->setName($data['name'] !== null ? (string) $data['name'] : null);
        }

        if (array_key_exists('description', $data)) {
            $human->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }

        return $human;
    }
}
