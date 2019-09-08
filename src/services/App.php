<?php
namespace pdaleramirez\superfilter\services;

use craft\base\Component;
use Craft;
use craft\base\Element;
use craft\db\Paginator;
use craft\elements\Category;
use craft\elements\db\EntryQuery;
use craft\web\twig\variables\Paginate;

class App extends Component
{
    static $pageSize = 1;
    protected $elements = [];
    protected $links;
    protected $elementQuery;
    public $params;

    public function config($params)
	{
	    $this->params = $params;

        $handle = $params['handle'];

        $categoryId = $params['category'];

        $limit = $params['limit'] ?? static::$pageSize;

        $elementsService = Craft::$app->getElements();

        /**
         * @var $elementClass Element
         */
        $elementClass = $elementsService->getElementTypeByRefHandle($handle);

        $category = null;
        $elementQuery = $elementClass::find();

        $section = $params['section'] ?? null;

        if ($handle == 'entry' && $section) {
            /**
             * @var $elementQuery EntryQuery
             */
            $elementQuery->section($section);
        }

        $elementQuery->orderBy(['id' => SORT_DESC]);

        if ($categoryId) {
            $category = Category::findOne($categoryId);
        }

        if ($category == null && $categoryId != null) {
            $category = Category::findOne(['slug' => $categoryId]);
        }

        if ($category) {
            $elementQuery = $elementQuery->relatedTo($category);
        }

        $this->elementQuery = $elementQuery;

        if ($category == null && $categoryId != null) {
           return [];
        }

        $currentPage = $params['currentPage'] ?? Craft::$app->getRequest()->getPageNum();

        $paginatorParams =  [
            'currentPage' => $currentPage
        ];

        if ($limit && $limit != "*") {
            $paginatorParams['pageSize'] = $limit;
        }

        $paginator = new Paginator($elementQuery, $paginatorParams);

        $this->links = Paginate::create($paginator);

        $items = $paginator->getPageResults();

        $this->params['total'] = $paginator->getTotalResults();

        if ($items) {
            /**
             * @var $item Element
             */
            foreach ($items as $item) {
                $fieldValues = $item->getFieldValues();
                $fieldValues = array_merge($fieldValues, ['title' => $item->title]);
                $this->elements[] = $fieldValues;
            }
        }

        return $this;
	}

	public function items()
    {
        return $this->elements;
    }

    /**
     * @return Paginate
     */
    public function links()
    {
        return $this->links;
    }

    public function query()
    {
        return $this->elementQuery;
    }

    public function setParams($params)
    {
        $this->params = $params;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getPageSize()
    {
        return static::$pageSize;
    }
}