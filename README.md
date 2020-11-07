# Rino

This: 
```
name:varchar{155}:not~null email:varchar{155}:not~null course_id:not~null
```

Can be transform on this:
```
<?php

class CreateUsersTable
{
    public function up()
    {
        return (
            'create table users (
                id int not null primary key auto_increment,
                name varchar(155) not null,
                email varchar(155) not null,
                course_id not null,
                foreign key (course_id) references courses(id)
            )'
        );
    }

    public function down()
    {
        return 'drop table users';
    }
}
```