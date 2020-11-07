<?php

class $MigrationName
{
    public function up()
    {
        return (
            'create table $table_name (
                $columns
            )'
        );
    }

    public function down()
    {
        return 'drop table $table_name';
    }
}