<?php namespace Caijw\Skills\TrueSkill\Layers;

use Caijw\Skills\FactorGraphs\FactorGraphLayer;
use Caijw\Skills\TrueSkill\TrueSkillFactorGraph;

abstract class TrueSkillFactorGraphLayer extends FactorGraphLayer
{
    public function __construct(TrueSkillFactorGraph $parentGraph)
    {
        parent::__construct($parentGraph);
    }
}
