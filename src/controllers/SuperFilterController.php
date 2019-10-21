<?php
namespace pdaleramirez\superfilter\controllers;


use barrelstrength\sproutbaseemail\elements\NotificationEmail;
use craft\elements\Entry;
use craft\errors\InvalidPluginException;
use craft\fields\PlainText;
use craft\helpers\Json;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\EntryType;
use craft\models\FieldGroup;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\web\Controller;
use Craft;
use pdaleramirez\superfilter\models\Settings;
use pdaleramirez\superfilter\services\App;
use pdaleramirez\superfilter\web\assets\VueAsset;
use pdaleramirez\superfilter\web\assets\VueCpAsset;
use craft\records\CategoryGroup as CategoryGroupRecord;

class SuperFilterController extends Controller
{
    /**
     * @return \yii\web\Response
     * @throws InvalidPluginException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionSettings()
    {
        Craft::$app->getView()->registerAssetBundle(VueCpAsset::class, 1);

        $plugin = Craft::$app->plugins->getPlugin('super-filter');

        if (!$plugin) {
            throw new InvalidPluginException($plugin->handle);
        }

        /**
         * @var $settings Settings
         */
        $settings = $plugin->getSettings();

        if (empty($settings->entryTemplate)) {
            $settings->entryTemplate = App::DEFAULT_TEMPLATE;
        }

        $selectedSidebarItem = Craft::$app->getRequest()->getSegment(3) ?? 'general';

        $templatePath = 'super-filter/settings/' . $selectedSidebarItem;

        return $this->renderTemplate($templatePath, [
            'settings' => $settings,
            'selectedSidebarItem' => $selectedSidebarItem
        ]);
    }

    /**
     * @return \yii\web\Response|null
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSaveSettingsConfig()
    {
        $postSettings = Craft::$app->getRequest()->getBodyParam('settings');

        $settings = $this->saveSettings($postSettings);
        if ($settings->hasErrors()) {
            Craft::$app->getSession()->setError(Craft::t('super-filter', 'Couldn’t save settings.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('super-filter', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    private function saveSettings($settings)
    {
        $plugin = Craft::$app->plugins->getPlugin('super-filter');
        // The existing settings
        $pluginSettings = $plugin->getSettings();

        $settings = $settings['settings'] ?? $settings;

        if (!$pluginSettings->validate()) {
            return $pluginSettings;
        }

        Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        return $pluginSettings;
    }

    public function actionTest()
    {
        \Craft::dd('xxx');
        $this->createCategoriesField();
       // $this->generateSampleData();
        exit;
//        $element = Entry::find()->section(1);
//
//        $enrtryTypes = Craft::$app->sections->getEntryTypesBySectionId(1);
//
//        $fields = $enrtryTypes[0]->getFieldLayout()->getFields();
//        \Craft::dd($fields);
        $elements = Craft::$app->getElements()->getAllElementTypes();

        \Craft::dd($elements);
        $entries = Entry::findAll(['sproutExamplePlainText' => 'xxx']);
        $entries = NotificationEmail::findAll(['sproutExamplePlainText' => 'xxx']);
        foreach ($entries as $entry) {

            echo $entry->title . '<br/>';
        }
        exit;
        \Craft::dd('asfd');
    }

    public function generateSampleData()
    {
        $entry = new Entry();
        $entry->sectionId = 2;
        $entry->typeId = 2;
        $entry->title = 'this is a test 2';
        $entry->setFieldValue('subTitle', 'xvasd');

        $result = Craft::$app->elements->saveElement($entry);

        \Craft::dd($result);
    }

    public function createCategoriesField()
    {
        $config["type"] = "craft\\fields\\Categories";
        $config["groupId"] = 1;
        $config["name"] = "Super Categories";
        $config["handle"] = "superCategories";

        $field = Craft::$app->getFields()->createField($config);

        \Craft::dd($field);
    }

    public function actionInstallSampleData()
    {
        $this->requirePostRequest();

        $ids = $this->createFields();

        $section = $this->createSection();

        $this->saveEntryType($section, $ids);


        return Json::encode(['result' => $ids]);
    }

    public function saveEntryType(Section $section, $ids)
    {
        $entryTypes = $section->getEntryTypes();

        $entryType  = $entryTypes[0];

        $test = [
          'Content' => $ids
        ];

        $fieldLayout = Craft::$app->getFields()->assembleLayout($test);
        //$fieldLayout->id = $fieldLayoutId;
        // Set the field layout
        //$fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Entry::class;
        $entryType->setFieldLayout($fieldLayout);

        if (!Craft::$app->getSections()->saveEntryType($entryType)) {

        }
    }

    public function createSection()
    {
        $handle = 'superFilterShows';
        $section = Craft::$app->getSections()->getSectionByHandle($handle);
        if (!$section) {
            $section = new Section();
            $section->name   = "Shows";
            $section->handle = $handle;
            $section->type   = "channel";
            $section->enableVersioning  = true;
            $section->propagationMethod = Section::PROPAGATION_METHOD_ALL;
            $section->previewTargets    = [];

            $sites = Craft::$app->getSites()->getAllSiteIds();

            $siteSettings = [];

            if ($sites) {
                foreach ($sites as $siteId) {
                    $sectionSiteSettings = new Section_SiteSettings();

                    $sectionSiteSettings->siteId = $siteId;
                    $sectionSiteSettings->hasUrls = true;
                    $sectionSiteSettings->uriFormat = 'super-filter-shows/{slug}';

                    $siteSettings[$siteId] = $sectionSiteSettings;
                }
            }

            $section->setSiteSettings($siteSettings);

            if (!Craft::$app->getSections()->saveSection($section)) {

                return false;
            }
        }

        return $section;
    }


    /**
     * @return |null
     * @throws \Throwable
     * @throws \craft\errors\CategoryGroupNotFoundException
     */
    private function createFields()
    {
        $fieldGroup = $this->getFieldGroup();
        $ids = [];
        $handle = 'superFilterDescription';

        $fieldDescription = Craft::$app->getFields()->getFieldByHandle($handle);

        if (!$fieldDescription) {
            $config  = [
                'type'    => PlainText::class,
                "groupId" => $fieldGroup->id,
                'name'    => 'Description',
                'handle'  => $handle,
                'multiline'   => true,
                "initialRows" => 4,
                "columnType"  => "text"
            ];

            $fieldDescription = Craft::$app->getFields()->createField($config);

            Craft::$app->getFields()->saveField($fieldDescription);
        }

        $ids[] = $fieldDescription->id;

        $handle = "superFilterGenre";

        $fieldGenre = Craft::$app->getFields()->getFieldByHandle($handle);

        if (!$fieldGenre) {
            $config = [
                "type"    => "craft\\fields\\Categories",
                "groupId" => $fieldGroup->id,
                "source" => 'group:' . $this->getCategoryGroup()->id,
                "name"    => "Genre",
                "handle"  => $handle
            ];

            $fieldGenre = Craft::$app->getFields()->createField($config);

            Craft::$app->getFields()->saveField($fieldGenre);
        }

        $ids[] = $fieldGenre->id;

        return $ids;
    }

    private function getFieldGroup()
    {
        $name = 'Super Filter';

        $group = new FieldGroup();

        $record = \craft\records\FieldGroup::find()->where([
                    'name'      => $name
                 ])->one();

        if ($record) {
            $group->setAttributes($record->getAttributes());

            return $group;
        }

        $group->name = $name;

        Craft::$app->getFields()->saveGroup($group);

        return $group;
    }

    /**
     * @return bool|CategoryGroup
     * @throws \Throwable
     * @throws \craft\errors\CategoryGroupNotFoundException
     */
    private function getCategoryGroup()
    {
        $categoryGroup = new CategoryGroup();
        $handle = 'superFilterGenre';

        $categoryGroupRecord = CategoryGroupRecord::find()
            ->where([
                'dateDeleted' => null,
                'handle'      => $handle
            ])->one();

        if ($categoryGroupRecord) {
            $categoryGroup->setAttributes($categoryGroupRecord->getAttributes());

            return $categoryGroup;
        }

        $categoryGroup->name   = 'Genre';
        $categoryGroup->handle = $handle;

        $siteSettings = [];

        $sites = Craft::$app->getSites()->getAllSiteIds();

        if ($sites) {
            foreach ($sites as $siteId) {
                $categorySiteSettings = new CategoryGroup_SiteSettings();

                $categorySiteSettings->siteId = $siteId;

                $siteSettings[$siteId] = $categorySiteSettings;
            }
        }

        $categoryGroup->setSiteSettings($siteSettings);

        Craft::$app->getCategories()->saveGroup($categoryGroup);

        return $categoryGroup;
    }
}
