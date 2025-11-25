<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function create(array $data)
    {
        return User::create($data);
    }

    public function findByEmail(string $email)
    {
        return User::where('email', $email)->first();
    }

    public function isEmailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    public function getAllUsers(int $perPage = 15)
    {
        return User::with('roles', 'entity')->paginate($perPage);
    }
    public function findById(int $id)
    {
        return User::with('roles', 'entity', 'permissions')->findOrFail($id);
    }

    public function delete(int $id)
    {
        return User::destroy($id);
    }

    public function update(User $user, array $newData)
    {
        $user->update($newData);
        return $user;
    }

    public function allEmployees()
    {
        return User::role('employee')->get();
    }

    public function allEmployeesWithFullData()
    {
        return User::role('employee')
            ->with('entity', 'permissions', 'roles')
            ->get();
    }
}
