<?php

namespace antonyz89\seeder;

use antonyz89\seeder\helpers\CreatedAtUpdatedAt;
use Faker\Factory;
use Faker\Generator;
use Faker\Provider\pt_BR\Address;
use Faker\Provider\pt_BR\Company;
use Faker\Provider\pt_BR\Person;
use Faker\Provider\pt_BR\PhoneNumber;
use Yii;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\Migration;
use yii\helpers\ArrayHelper;

/**
 * Class TableSeeder
 * @package console\seeder
 *
 * @property Generator|Factory $faker
 * @property boolean $skipTruncateTables
 */
abstract class TableSeeder extends Migration
{
    use CreatedAtUpdatedAt;

    public $faker;
    public $skipTruncateTables = false;
    private $insertedColumns = [];
    private $batch = [];

    /**
     * TableSeeder constructor.
     * @param array $config
     * @throws Exception
     * @throws NotSupportedException
     */
    public function __construct(array $config = [])
    {
        $this->faker = \Faker\Factory::create(Yii::$app->language);

        parent::__construct($config);
    }

    public function __destruct()
    {
        if (!$this->skipTruncateTables) {
            $this->disableForeignKeyChecks();
            foreach ($this->batch as $table => $values)
                $this->truncateTable($table);
            $this->enableForeignKeyChecks();
        }

        foreach ($this->batch as $table => $values) {
            $total = 0;
            foreach ($values as $columns => $rows) {
                $total += count($rows);
                $this->batchInsert($table, explode(',', $columns), $rows);
            }
            echo "      $total row" . ($total > 1 ? 's' : null) . " inserted  in $table" . "\n";
        }
        self::checkMissingColumns($this->insertedColumns);
    }

    abstract function run();

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function disableForeignKeyChecks()
    {
        Yii::$app->db->createCommand()->checkIntegrity(false)->execute();
    }

    /**
     * @throws Exception
     * @throws NotSupportedException
     */
    public function enableForeignKeyChecks()
    {
        Yii::$app->db->createCommand()->checkIntegrity(true)->execute();
    }

    public function insert($table, $columns)
    {
        $columnNames = Yii::$app->db->getTableSchema($table)->columnNames;

        $this->generate();

        if (in_array('created_at', $columnNames) && !in_array('created_at', array_keys($columns)))
            $columns['created_at'] = $this->createdAt;

        if (in_array('updated_at', $columnNames) && !in_array('updated_at', array_keys($columns)))
            $columns['updated_at'] = $this->updatedAt;

        $this->insertedColumns[$table] = ArrayHelper::merge(
            array_keys($columns),
            isset($this->insertedColumns[$table]) ? $this->insertedColumns[$table] : []
        );

        $this->batch[$table][implode(',', array_keys($columns))][] = array_values($columns);
    }

    public function truncateTable($table)
    {
        $this->db = Yii::$app->db;
        parent::truncateTable($table);
    }

    private static function checkMissingColumns($insertedColumns)
    {
        $missingColumns = [];

        foreach ($insertedColumns as $table => $columns) {
            $tableColumns = Yii::$app->db->getTableSchema($table)->columns;

            foreach ($tableColumns as $tableColumn) {
                if (!$tableColumn->autoIncrement && !in_array($tableColumn->name, $columns))
                    $missingColumns[$table][] = [$tableColumn->name, $tableColumn->dbType];
            }
        }

        if (count($missingColumns)) {
            echo "    > " . str_pad(' MISSING COLUMNS ', 70, '#', STR_PAD_BOTH) . "\n";
            foreach ($missingColumns as $table => $columns) {
                echo "    > " . str_pad("# TABLE: $table", 69, ' ') . "#\n";
                foreach ($columns as [$tableColumn, $type])
                    echo "    > " . str_pad("#    $tableColumn => $type", 69, ' ') . "#\n";
            }
            echo "    > " . str_pad('', 70, '#') . "\n";
        }
    }

    public static function create()
    {
        return new static();
    }
}
