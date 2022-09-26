<?php namespace Caijw\Skills\TrueSkill;

use Caijw\Skills\GameInfo;
use Caijw\Skills\Numerics\GaussianDistribution;
use Caijw\Skills\Rating;
use Caijw\Skills\RatingContainer;
use Caijw\Skills\FactorGraphs\FactorGraph;
use Caijw\Skills\FactorGraphs\FactorList;
use Caijw\Skills\FactorGraphs\ScheduleSequence;
use Caijw\Skills\FactorGraphs\VariableFactory;
use Caijw\Skills\TrueSkill\Layers\IteratedTeamDifferencesInnerLayer;
use Caijw\Skills\TrueSkill\Layers\PlayerPerformancesToTeamPerformancesLayer;
use Caijw\Skills\TrueSkill\Layers\PlayerPriorValuesToSkillsLayer;
use Caijw\Skills\TrueSkill\Layers\PlayerSkillsToPerformancesLayer;
use Caijw\Skills\TrueSkill\Layers\TeamDifferencesComparisonLayer;
use Caijw\Skills\TrueSkill\Layers\TeamPerformancesToTeamPerformanceDifferencesLayer;

class TrueSkillFactorGraph extends FactorGraph
{
    private $_gameInfo;
    private $_layers;
    private $_priorLayer;

    public function __construct(GameInfo $gameInfo, array $teams, array $teamRanks)
    {
        $this->_priorLayer = new PlayerPriorValuesToSkillsLayer($this, $teams);
        $this->_gameInfo = $gameInfo;
        $newFactory = new VariableFactory(
            function () {
                return GaussianDistribution::fromPrecisionMean(0, 0);
            });

        $this->setVariableFactory($newFactory);
        $this->_layers = array(
            $this->_priorLayer,
            new PlayerSkillsToPerformancesLayer($this),
            new PlayerPerformancesToTeamPerformancesLayer($this),
            new IteratedTeamDifferencesInnerLayer(
                $this,
                new TeamPerformancesToTeamPerformanceDifferencesLayer($this),
                new TeamDifferencesComparisonLayer($this, $teamRanks))
        );
    }

    public function getGameInfo()
    {
        return $this->_gameInfo;
    }

    public function buildGraph()
    {
        $lastOutput = null;

        $layers = $this->_layers;
        foreach ($layers as $currentLayer) {
            if ($lastOutput != null) {
                $currentLayer->setInputVariablesGroups($lastOutput);
            }

            $currentLayer->buildLayer();

            $lastOutput = $currentLayer->getOutputVariablesGroups();
        }
    }

    public function runSchedule()
    {
        $fullSchedule = $this->createFullSchedule();
        $fullScheduleDelta = $fullSchedule->visit();
    }

    public function getProbabilityOfRanking()
    {
        $factorList = new FactorList();

        $layers = $this->_layers;
        foreach ($layers as $currentLayer) {
            $localFactors = $currentLayer->getLocalFactors();
            foreach ($localFactors as $currentFactor) {
                $localCurrentFactor = $currentFactor;
                $factorList->addFactor($localCurrentFactor);
            }
        }

        $logZ = $factorList->getLogNormalization();
        return exp($logZ);
    }

    private function createFullSchedule()
    {
        $fullSchedule = array();

        $layers = $this->_layers;
        foreach ($layers as $currentLayer) {
            $currentPriorSchedule = $currentLayer->createPriorSchedule();
            if ($currentPriorSchedule != null) {
                $fullSchedule[] = $currentPriorSchedule;
            }
        }

        $allLayersReverse = array_reverse($this->_layers);

        foreach ($allLayersReverse as $currentLayer) {
            $currentPosteriorSchedule = $currentLayer->createPosteriorSchedule();
            if ($currentPosteriorSchedule != null) {
                $fullSchedule[] = $currentPosteriorSchedule;
            }
        }

        return new ScheduleSequence("Full schedule", $fullSchedule);
    }

    public function getUpdatedRatings()
    {
        $result = new RatingContainer();

        $priorLayerOutputVariablesGroups = $this->_priorLayer->getOutputVariablesGroups();
        foreach ($priorLayerOutputVariablesGroups as $currentTeam) {
            foreach ($currentTeam as $currentPlayer) {
                $localCurrentPlayer = $currentPlayer->getKey();
                $newRating = new Rating($currentPlayer->getValue()->getMean(),
                    $currentPlayer->getValue()->getStandardDeviation());

                $result->setRating($localCurrentPlayer, $newRating);
            }
        }

        return $result;
    }
}
