<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\AbTesting\Activity;

class ExperimentStarted extends BaseActivity
{
    protected $eventName = 'AbTesting.startExperiment';

    public function extractParams($eventData)
    {
        if (empty($eventData[0]) || empty($eventData[1])) {
            return false;
        }

        $idExperiment = $eventData[0];
        $idSite = $eventData[1];

        return $this->formatActivityData($idExperiment, $idSite);
    }

    public function getTranslatedDescription($activityData, $performingUser)
    {
        $siteName = $this->getSiteNameFromActivityData($activityData);

        $experimentName = $this->getExperimentNameFromActivityData($activityData);

        $desc = sprintf('started the experiment "%1$s" for site "%2$s"', $experimentName, $siteName);

        return $desc;
    }
}
