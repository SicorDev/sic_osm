<?php
namespace SICOR\SicOsm\Evaluation;

/**
 * Latitude in decimal degrees, -90 to 90
 */
class LatitudeEvaluation extends AbstractCoordinateEvaluation
{
    protected const LIMIT = 90.0;
}
