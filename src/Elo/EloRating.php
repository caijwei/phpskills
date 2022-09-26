<?php namespace Caijw\Skills\Elo;

use Caijw\Skills\Rating;

/**
 * An Elo rating represented by a single number (mean).
 */
class EloRating extends Rating
{
    public function __construct($rating)
    {
        parent::__construct($rating, 0);
    }
}
