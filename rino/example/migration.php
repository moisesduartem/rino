<?php

class $MigrationName
{
    public function up()
    {
        return (
            'create table $table_name (
                id int not null primary key auto_increment$columns
            )'
        );
    }

    public function down()
    {
        return 'drop table $table_name';
    }
}