<?php namespace Caijw\Skills\Elo;

use Exception;
use Caijw\Skills\GameInfo;
use Caijw\Skills\PairwiseComparison;
use Caijw\Skills\RankSorter;
use Caijw\Skills\SkillCalculator;
use Caijw\Skills\SkillCalculatorSupportedOptions;
use Caijw\Skills\PlayersRange;
use Caijw\Skills\TeamsRange;

abstract class TwoPlayerEloCalculator extends SkillCalculator
{
    protected $_kFactor;

    protected function __construct(KFactor $kFactor)
    {
        parent::__construct(SkillCalculatorSupportedOptions::NONE, TeamsRange::exactly(2), PlayersRange::exactly(1));
        $this->_kFactor = $kFactor;
    }

    public function calculateNewRatings(GameInfo $gameInfo, array $teamsOfPlayerToRatings, array $teamRanks)
    {
        $this->validateTeamCountAndPlayersCountPerTeam($teamsOfPlayerToRatings);
        RankSorter::sort($teamsOfPlayerToRatings, $teamRanks);

        $result = array();
        $isDraw = ($teamRanks[0] === $teamRanks[1]);

        $team1 = $teamsOfPlayerToRatings[0];
        $team2 = $teamsOfPlayerToRatings[1];

        $player1 = reset($team1);
        $player2 = reset($team2);

        $player1Rating = $player1->getMean();
        $player2Rating = $player2->getMean();

        $result[key($team1)] = $this->calculateNewRating($gameInfo, $player1Rating, $player2Rating, $isDraw ? PairwiseComparison::DRAW : PairwiseComparison::WIN);
        $result[key($team2)] = $this->calculateNewRating($gameInfo, $player2Rating, $player1Rating, $isDraw ? PairwiseComparison::DRAW : PairwiseComparison::LOSE);

        return $result;
    }

    protected function calculateNewRating($gameInfo, $selfRating, $opponentRating, $selfToOpponentComparison)
    {
        $expectedProbability = $this->getPlayerWinProbability($gameInfo, $selfRating, $opponentRating);
        $actualProbability = $this->getScoreFromComparison($selfToOpponentComparison);
        $k = $this->_kFactor->getValueForRating($selfRating);
        $ratingChange = $k * ($actualProbability - $expectedProbability);
        $newRating = $selfRating + $ratingChange;

        return new EloRating($newRating);
    }

    private static function getScoreFromComparison($comparison)
    {
        switch ($comparison) {
            case PairwiseComparison::WIN:
                return 1;
            case PairwiseComparison::DRAW:
                return 0.5;
            case PairwiseComparison::LOSE:
                return 0;
            default:
                throw new Exception("Unexpected comparison");
        }
    }

    public abstract function getPlayerWinProbability(GameInfo $gameInfo, $playerRating, $opponentRating);

    public function calculateMatchQuality(GameInfo $gameInfo, array $teamsOfPlayerToRatings)
    {
        $this->validateTeamCountAndPlayersCountPerTeam($teamsOfPlayerToRatings);
        $team1 = $teamsOfPlayerToRatings[0];
        $team2 = $teamsOfPlayerToRatings[1];

        $player1 = $team1[0];
        $player2 = $team2[0];

        $player1Rating = $player1[1]->getMean();
        $player2Rating = $player2[1]->getMean();

        $ratingDifference = $player1Rating - $player2Rating;

        // The TrueSkill paper mentions that they used s1 - s2 (rating difference) to
        // determine match quality. I convert that to a percentage as a delta from 50%
        // using the cumulative density function of the specific curve being used
        $deltaFrom50Percent = abs($this->getPlayerWinProbability($gameInfo, $player1Rating, $player2Rating) - 0.5);
        return (0.5 - $deltaFrom50Percent) / 0.5;
    }
}
