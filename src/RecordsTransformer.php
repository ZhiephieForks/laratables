<?php

namespace Freshbitsweb\Laratables;

class RecordsTransformer
{
    protected $model;

    protected $columnManager;

    /**
     * Initialize properties.
     *
     * @param \Illuminate\Database\Eloquent\Model The model to work on
     *
     * @return void
     */
    public function __construct($model, $columnManager)
    {
        $this->model = $model;
        $this->columnManager = $columnManager;
    }

    /**
     * Transforms each record for Datatables display.
     *
     * @param \Illuminate\Support\Collection Records of the table
     *
     * @return \Illuminate\Support\Collection Records of the table
     */
    public function transformRecords($records)
    {
        if (method_exists($this->model, 'laratablesModifyCollection')) {
            $records = $this->model::laratablesModifyCollection($records)->values();
        }

        return $records->map(function ($item) {
            return $this->transformRecord($item);
        });
    }

    /**
     * Transform the record data for Datatables display.
     *
     * @param \Illuminate\Database\Eloquent\Model Eloquent object
     *
     * @return \Illuminate\Database\Eloquent\Model Eloquent object
     */
    protected function transformRecord($record)
    {
        $columnNames = $this->columnManager->getRequestedColumnNames();
        $columnNames->transform(function ($item) use ($record) {
            return $this->getColumnValue($item, $record);
        });

        $datatableParameters = $this->getDatatableParameters($record);

        return array_merge($datatableParameters, $columnNames->toArray());
    }

    /**
     * Retuns column value to be displayed in datatables.
     *
     * @param mixed Column value from database
     * @param \Illuminate\Database\Eloquent\Model Eloquent object
     *
     * @return string
     */
    protected function getColumnValue($columnName, $record)
    {
        // Set custom column value from the model static method
        if ($methodName = $this->columnManager->isCustomColumn($columnName)) {
            return $this->model::$methodName($record);
        }

        if ($methodName = $this->customisesColumnValue($columnName)) {
            return $record->$methodName();
        }

        if (isRelationColumn($columnName)) {
            return $this->getRelationColumnValue($columnName, $record);
        }

        if ($this->isCarbonInstance($record->$columnName)) {
            return $record->$columnName->format(config('laratables.date_format', 'Y-m-d H:i:s'));
        }

        return $record->$columnName;
    }

    /**
     * Decides whether there is a custom method on the class for the specified column. Returns method name if yes.
     *
     * @param string Name of the column
     *
     * @return bool|string
     */
    protected function customisesColumnValue($columnName)
    {
        $methodName = camel_case('laratables_'.$columnName);

        if (method_exists($this->model, $methodName)) {
            return $methodName;
        }

        return false;
    }

    /**
     * Returns the value of relation table column.
     *
     * @param string Name of the column
     * @param \Illuminate\Database\Eloquent\Model Eloquent object
     *
     * @return string
     */
    protected function getRelationColumnValue($columnName, $record)
    {
        list($relationName, $relationColumnName) = getRelationDetails($columnName);

        if ($methodName = $this->customisesColumnValue($relationName.'_'.$relationColumnName)) {
            return $record->$methodName();
        }

        if ($record->$relationName) {
            return $record->$relationName->$relationColumnName;
        }

        return 'N/A';
    }

    /**
     * Decides whether provided column value is a carbon date instance.
     *
     * @param mmixed Column value
     *
     * @return bool
     */
    protected function isCarbonInstance($columnValue)
    {
        return is_object($columnValue) &&
            $columnValue instanceof \Illuminate\Support\Carbon
        ;
    }

    /**
     * Returns the datatable specific parameters for the record.
     *
     * @param \Illuminate\Database\Eloquent\Model Eloquent object
     *
     * @return array
     */
    public function getDatatableParameters($record)
    {
        $datatableParameters = [
            'DT_RowId' => config('laratables.row_id_prefix').$record->{$record->getKeyName()},
        ];

        if (method_exists($this->model, 'laratablesRowClass')) {
            $datatableParameters['DT_RowClass'] = $record->laratablesRowClass();
        }

        if (method_exists($this->model, 'laratablesRowData')) {
            $datatableParameters['DT_RowData'] = $record->laratablesRowData();
        }

        return $datatableParameters;
    }
}
