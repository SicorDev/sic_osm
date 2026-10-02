<?php
namespace SICOR\SicOsm\Evaluation;

/**
 * Longitude in decimal degrees, -180 to 180
 */
class LongitudeEvaluation extends AbstractCoordinateEvaluation
{
    protected const LIMIT = 180.0;
}
