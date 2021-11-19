<?php

namespace Statamic\Policies;

use Statamic\Facades\User;

class GraphQLPolicy
{
    public function before($user, $ability)
    {
        $user = User::fromUser($user);

        if ($user->hasPermission('configure graphql')) {
            return true;
        }
    }

    public function index($user)
    {
        $user = User::fromUser($user);

        if ($this->create($user)) {
            return true;
        }

        return false;
    }

    public function create($user)
    {
        // handled by before()
    }

    public function view($user, $form)
    {
        // handled by before()
    }

    public function edit($user, $form)
    {
        // handled by before()
    }

    public function delete($user, $form)
    {
        // handled by before()
    }
}
