<?php

namespace App\Exceptions;

use RuntimeException;

class RestaurantReviewOwnershipException extends RuntimeException
{
    public const MESSAGE = 'Vous gérez ce restaurant, vous ne pouvez pas publier d’avis sur votre propre établissement.';
}
