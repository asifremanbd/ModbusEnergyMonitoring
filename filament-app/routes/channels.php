<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channels for real-time monitoring
Broadcast::channel('readings', function () {
    return true; // Public channel for all authenticated users
});

Broadcast::channel('gateways', function () {
    return true; // Public channel for all authenticated users
});

Broadcast::channel('gateway.{gatewayId}', function ($user, $gatewayId) {
    return true; // Public channel for specific gateway updates
});
