<?php

namespace pdaleramirez\superfilter\controllers;

use craft\db\Paginator;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use Craft;
use pdaleramirez\superfilter\services\App;
use pdaleramirez\superfilter\SuperFilter;

class ElementsController extends Controller
{
    protected $allowAnonymous = ['get-fields', 'filter'];

    public function actionGetFields()
    {
        $handle = Craft::$app->getRequest()->getBodyParam('handle');
		$this->setItemAttributes();
        $searchSetupService = SuperFilter::$app->searchTypes;

        $config = $searchSetupService->getConfigById($handle);

        $requestParams = Craft::$app->getRequest()->getBodyParam('config.params');

        if ($requestParams) {
            $config['params'] = $requestParams;
        }

        $searchSetupService->setSearchSetup($config);

        $config = $searchSetupService->getConfig();

        $config['params']['fields'] =  $searchSetupService->getInitFields($config);

        return Json::encode([
            'config' => $config,
            'links' => $searchSetupService->getLinks(),
            'items' => $searchSetupService->getItemToArray(),
            'query' => ''
        ]);
    }

	private function setItemAttributes()
	{
		$searchSetupService = SuperFilter::$app->searchTypes;
		$itemAttributes = Craft::$app->getRequest()->getBodyParam('itemAttributes');

		if ($itemAttributes !== null) {
			$itemAttributes = Json::decode($itemAttributes);

			if ($itemAttributes) {
				$searchSetupService->setItemAttributes($itemAttributes);
			}
		}
	}

    public function actionFilter()
    {
        $handle = Craft::$app->getRequest()->getBodyParam('handle');
		$this->setItemAttributes();
        $searchSetupService = SuperFilter::$app->searchTypes;

        $config = $searchSetupService->getConfigById($handle);

        $initSort = $config['options']['initSort'] ?? null;

        $requestConfig = Craft::$app->getRequest()->getBodyParam('config');

        $config = array_merge($config, $requestConfig);

        $fields = $config['params']['fields'] ?? null;

        if ($fields) {
            foreach ($fields as $handle => $field) {
                $fieldValue = is_string($field) ? trim($field) : $field;

                if (is_string($field) && $fieldValue === '') {
                    unset($config['params']['fields'][$handle]);
                }
            }
        }

        $searchSetupService->setSearchSetup($config);

        $query = '';

        if (!empty($config['params']['fields']) || !empty($config['params']['sort'])) {

            if ($initSort && $initSort == $config['params']['sort']) {
                unset($config['params']['sort']);
            }

            if ($fields !== null && !empty(SuperFilter::$app->getSettings()->prefixParam)) {
                $config['params'][SuperFilter::$app->getSettings()->prefixParam] = $config['params']['fields'];
            }

            // Does not need to be in the url
            if (isset($config['params']['siteId'])) {
                unset($config['params']['siteId']);
            }

            $query = App::buildQuery($config['params']);

        }

        return Json::encode([
            'items' => $searchSetupService->getItemToArray(),
            'links' => $searchSetupService->getLinks(),
            'query' => $query
        ]);
    }
}
