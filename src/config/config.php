<?php

return array(

    /*
    |--------------------------------------------------------------------------------
    | Joins
    |--------------------------------------------------------------------------------
    | Sometimes we need to make joins to create the necessary queries.
    | Because it's hard to create proper queries from the inputted parameters
    | the configuration below will be used to create the joins.
    |
    | Be sure all the tables you are using for filtering have join configuration!
    |
    | Example:
     'joins' => array(
        'maintenances|elements' => array(
            'left' => 'maintenances.element_id',
            'operand' => '=',
            'right' => 'elements.id'
        ),
        'elements|element_flora' => array(
            'left' => 'element_flora.element_id',
            'operand' => '=',
            'right' => 'elements.id'
        )
     );
    |
    | model1|model2 is the choosen name convention that has to be used for the joins.
    */

    'joins' => array(),

    /*
    |--------------------------------------------------------------------------------
    | Titles
    |--------------------------------------------------------------------------------
    | Configure the name of your refinements here.
    | If no name is entered the standard naming format will be used.
    |
    | Example:
        'titles' => array(
            'elements|location' => 'Locatie',
            'elements|element_type' => 'Element'
        );
    |
    | table_name|table_column is the choosen name convention that has to be used for the titles.
    */

    'titles' => array(),
);