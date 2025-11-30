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

    // public function allEmployeesWithFullData()
    // {
    //     return User::role('employee')
    //         ->with('entity', 'permissions', 'roles')
    //         ->get();
    // }

    public function findCitizenById(int $id)
    {
        return User::whereHas('roles', function ($query) {
            $query->where('name', 'citizen');
        })->find($id);
    }



    public function getUsersByType(string $type, int $perPage = 15)
    {
        return match ($type) {
            'citizen' => User::role('citizen')->latest()->paginate($perPage),
            'employee' => User::role('employee')->with('entity')->latest()->paginate($perPage),
            'all' => User::whereDoesntHave('roles', fn($q) => $q->where('name', 'admin'))
                ->orWhere('id', auth()->id()) // عشان ما يستثني نفسه لو كان admin
                ->latest()
                ->paginate($perPage),
            default => User::latest()->paginate($perPage),
        };
    }

    // دالة أنظف وأكثر مرونة (موصى بها)
    public function getFilteredUsers(string $type = 'all', int $perPage = 15, ?User $currentUser = null)
    {
        $query = User::query();

        match ($type) {
            'citizen' => $query->role('citizen'),
            'employee' => $query->role('employee')->with('entity'),
            'all' => $query->whereDoesntHave('roles', fn($q) => $q->where('name', 'admin')),
            default => null,
        };

        // نستثني الـ admin الحالي فقط إذا كان type = all وهو admin فعلاً
        if ($type === 'all' && $currentUser?->hasRole('admin')) {
            $query->where('id', '!=', $currentUser->id);
        }

        return $query->latest()->paginate($perPage);
    }
}
