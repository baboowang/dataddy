<?php
namespace MY;

class Data_ArrayTable
{
    private $dataset;
    private $columns;

    public function __construct($dataset)
    {
        $this->dataset = $dataset;
        $this->columns = $this->getColumns($dataset);
    }

    private function getColumns(array $dataset)
    {
        if (empty($dataset)) {
            throw new \Exception("ArrayTable::getColumns param dataset is empty");
        }

        return array_keys($dataset[array_shift(array_keys($dataset))]);
    }

    private function getRowKey($row, $keys)
    {
        $values = [];
        foreach ($keys as $key) {
            if (!isset($row[$key])) {
                throw new \Exception("ArrayTable::getRowKey {$key} not exists in row");
            }
            $values[] = $row[$key];
        }

        return implode('|', $values);
    }

    public function union($dataset, $all = true)
    {
        if (empty($dataset)) {
            return false;
        }

        $columns = $this->getColumns($dataset);

        $extra_columns = array_diff($columns, $this->columns);

        if ($extra_columns) {
            foreach ($this->dataset as $i => $row) {
                foreach ($extra_columns as $column) {
                    $this->dataset[$i][$column] = '';
                }
            }
            $this->columns = array_merge($this->columns, $extra_columns);
        }

        foreach ($dataset as $row) {
            $new_row = [];
            if ($extra_columns || count($columns) != count($this->columns)) {
                foreach ($this->columns as $column) {
                    $new_row[$column] = $row[$column] ?? '';
                }
            }
            $this->dataset[] = $new_row;
        }

        return $this;
    }

    public function join($dataset, $keys = [], $full_join = false)
    {
        if (empty($dataset)) {
            return false;
        }

        $columns = $this->getColumns($dataset);

        if (!$keys) {
            $keys = array_intersect($this->columns, $columns);
        }

        if (!$keys) {
            throw new \Exception("ArrayTable::leftJoin no keys");
        }

        $extra_columns = array_diff($columns, $keys);

        if (count($columns) - count($extra_columns) != count($keys)) {
            throw new \Exception("ArrayTable::leftJion keys error");
        }

        $key_dataset = [];
        foreach ($dataset as $row) {
            $key_dataset[$this->getRowKey($row, $keys)] = $row;
        }

        foreach ($this->dataset as &$row) {
            $row_key = $this->getRowKey($row, $keys);
            $right_row = $key_dataset[$row_key] ?? [];
            foreach ($extra_columns as $column) {
                $row[$column] = $right_row[$column] ?? '';
            }
            unset($key_dataset[$row_key]);
        }

        unset($row);

        $this->columns = array_merge($this->columns, $extra_columns);

        if ($full_join && $key_dataset) {
            foreach ($key_dataset as $row) {
                $new_row = [];
                foreach ($this->columns as $column) {
                    $new_row[$column] = $row[$column] ?? '';
                }
                $this->dataset[] = $new_row;
            }
        }

        return $this;
    }

    public function getDataset()
    {
        return $this->dataset;
    }
}
