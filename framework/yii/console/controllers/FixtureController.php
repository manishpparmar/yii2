<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\console\controllers;

use Yii;
use yii\console\Controller;
use yii\console\Exception;
use yii\test\DbTestTrait;
use yii\helpers\Console;

/**
 * This command manages fixtures load to the database tables.
 * You can specify different options of this command to point fixture manager
 * to the specific tables of the different database connections.
 *
 * To use this command simply configure your console.php config like this:
 *
 * ~~~
 * 'db' => [
 *     'class' => 'yii\db\Connection',
 *     'dsn' => 'mysql:host=localhost;dbname={your_database}',
 *     'username' => '{your_db_user}',
 *     'password' => '',
 *     'charset' => 'utf8',
 * ],
 * 'fixture' => [
 *     'class' => 'yii\test\DbFixtureManager',
 * ],
 * ~~~
 *
 * ~~~
 * #load fixtures under $fixturePath to the "users" table
 * yii fixture/apply users
 *
 * #also a short version of this command (generate action is default)
 * yii fixture users
 *
 * #load fixtures under $fixturePath to the "users" table to the different connection
 * yii fixture/apply users --db=someOtherDbConneciton
 *
 * #load fixtures under different $fixturePath to the "users" table.
 * yii fixture/apply users --fixturePath=@app/some/other/path/to/fixtures
 * ~~~
 *
 * @author Mark Jebri <mark.github@yandex.ru>
 * @since 2.0
 */
class FixtureController extends Controller
{
	use DbTestTrait;

	/**
	 * @var string controller default action ID.
	 */
	public $defaultAction = 'apply';
	/**
	 * Alias to the path, where all fixtures are stored.
	 * @var string
	 */
	public $fixturePath = '@tests/unit/fixtures';
	/**
	 * Id of the database connection component of the application.
	 * @var string
	 */
	public $db = 'db';


	/**
	 * Returns the names of the global options for this command.
	 * @return array the names of the global options for this command.
	 */
	public function globalOptions()
	{
		return array_merge(parent::globalOptions(), [
			'db', 'fixturePath'
		]);
	}

	/**
	 * This method is invoked right before an action is to be executed (after all possible filters.)
	 * It checks that fixtures path and database connection are available.
	 * @param \yii\base\Action $action
	 * @return boolean
	 */
	public function beforeAction($action)
	{
		if (parent::beforeAction($action)) {
			$this->checkRequirements();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Apply given fixture to the table. You can load several fixtures specifying
	 * their names separated with commas, like: tbl_user,tbl_profile. Be sure there is no
	 * whitespace between tables names.
	 * @param array $fixtures
	 * @throws \yii\console\Exception
	 */
	public function actionApply(array $fixtures)
	{
		if ($this->getFixtureManager() === null) {
			throw new Exception('Fixture manager is not configured properly. Please refer to official documentation for this purposes.');
		}

		if (!$this->confirmApply($fixtures)) {
			return;
		}

		$this->getFixtureManager()->basePath = $this->fixturePath;
		$this->getFixtureManager()->db = $this->db;
		$this->loadFixtures($fixtures);
		$this->notifySuccess($fixtures);
	}

	/**
	 * Truncate given table and clear all fixtures from it. You can clear several tables specifying
	 * their names separated with commas, like: tbl_user,tbl_profile. Be sure there is no
	 * whitespace between tables names.
	 * @param array|string $tables
	 */
	public function actionClear(array $tables)
	{
		if (!$this->confirmClear($tables)) {
			return;
		}

		foreach($tables as $table) {
			$this->getDbConnection()->createCommand()->truncateTable($table)->execute();
			$this->stdout("Table \"{$table}\" was successfully cleared. \n", Console::FG_GREEN);
		}
	}

	/**
	 * Checks if the database and fixtures path are available.
	 * @throws Exception
	 */
	public function checkRequirements()
	{
		$path = Yii::getAlias($this->fixturePath, false);

		if (!is_dir($path) || !is_writable($path)) {
			throw new Exception("The fixtures path \"{$this->fixturePath}\" not exist or is not writable.");
		}

	}

	/**
	 * Returns database connection component
	 * @return \yii\db\Connection
	 * @throws Exception if [[db]] is invalid.
	 */
	public function getDbConnection()
	{
		$db = Yii::$app->getComponent($this->db);

		if ($db === null) {
			throw new Exception("There is no database connection component with id \"{$this->db}\".");
		}

		return $db;
	}

	/**
	 * Notifies user that fixtures were successfully loaded.
	 * @param array $fixtures
	 */
	private function notifySuccess($fixtures)
	{
		$this->stdout("Fixtures were successfully loaded from path:\n", Console::FG_YELLOW);
		$this->stdout(Yii::getAlias($this->fixturePath) . "\n\n", Console::FG_GREEN);
		$this->outputList($fixtures);
	}

	/**
	 * Prompts user with confirmation if fixtures should be loaded.
	 * @param array $fixtures
	 * @return boolean
	 */
	private function confirmApply($fixtures)
	{
		$this->stdout("Fixtures will be loaded from path: \n", Console::FG_YELLOW);
		$this->stdout(Yii::getAlias($this->fixturePath) . "\n\n", Console::FG_GREEN);
		$this->outputList($fixtures);
		return $this->confirm('Load to database above fixtures?');
	}

	/**
	 * Prompts user with confirmation for tables that should be cleared.
	 * @param array $tables
	 * @return boolean
	 */
	private function confirmClear($tables)
	{
		$this->stdout("Tables below will be cleared:\n\n", Console::FG_YELLOW);
		$this->outputList($tables);
		return $this->confirm('Clear tables?');
	}

	/**
	 * Outputs data to the console as a list.
	 * @param array $data
	 */
	private function outputList($data)
	{
		foreach($data as $index => $item) {
			$this->stdout(($index + 1) . ". {$item}\n", Console::FG_GREEN);
		}
	}
}
