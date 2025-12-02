<?php

namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;

class AdminUserService
{
    protected $repo;

    public function __construct(UserRepository $repo)
    {
        $this->repo = $repo;
    }

    public function createEmployee(array $data)
    {
        return DB::transaction(function () use ($data) {

            $data['password'] = bcrypt($data['password']);
            $user = $this->repo->create($data);
            $user->assignRole('employee');

            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $user->syncPermissions($data['permissions']);
            }

            return $user->load('entity', 'permissions');
        });
    }


    public function updateEmployee(int $id, array $data)
    {
        $user = $this->repo->findById($id);

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $updated = $this->repo->update($user, $data);

        return $updated->load('entity', 'permissions');
    }


    public function updatePermissions(int $id, array $permissions)
    {
        $user = $this->repo->findById($id);

        $user->syncPermissions($permissions);

        return $user->load('entity', 'permissions');
    }


    public function deleteEmployee(int $id)
    {
        return $this->repo->delete($id);
    }
}
