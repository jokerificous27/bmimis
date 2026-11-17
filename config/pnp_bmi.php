<?php

/*
|--------------------------------------------------------------------------
| PNP Acceptable BMI — exemptions for overweight personnel
|--------------------------------------------------------------------------
|
| The BMI bands per age group ("PNP Acceptable BMI Classification, PNP HS Recommended") are stored
| in the database as a BMI standard and are edited in Administration → BMI Standards.
|
| The two rules below come from the PNP Health Service guides "Acceptable BMI by Large Body Frame"
| and "Acceptable BMI by Waist Circumference". They apply to personnel whose BMI is above the
| acceptable range for their age, and both require that there is NO bulging abdomen.
|
*/

return [

    /*
    | Body frame from the height-and-wrist ratio: height (cm) ÷ wrist (cm), cut to one decimal as in
    | the Health Service example (165 ÷ 18 = 9.1). Below "large_below" is a large frame, above
    | "small_above" a small frame, anything between a medium frame.
    */
    'frame' => [
        'female' => ['large_below' => 9.9, 'small_above' => 10.9],
        'male' => ['large_below' => 9.6, 'small_above' => 10.4],
    ],

    /*
    | Highest BMI accepted for a LARGE body frame, per age group: the acceptable BMI for the age plus
    | "10% median of ideal body weight" (24.9 + 2.49, 25 + 2.50, 25.5 + 2.52, 26 + 2.55, 26.5 + 2.57,
    | 27 + 2.60). "max_age" null means "and above". Listed youngest first.
    */
    'large_frame_max_bmi' => [
        ['max_age' => 29, 'bmi' => 27.39],
        ['max_age' => 34, 'bmi' => 27.5],
        ['max_age' => 39, 'bmi' => 28.02],
        ['max_age' => 44, 'bmi' => 28.55],
        ['max_age' => 50, 'bmi' => 29.07],
        ['max_age' => null, 'bmi' => 29.6],
    ],

    /*
    | Waist circumference cut-off (cm, "at most") for OVERWEIGHT personnel with an athletic or
    | muscular physique. It does not apply to obese personnel (BMI of "overweight_below_bmi" or more).
    | The guide gives inches: 31 / 35 in. up to 44 years old, 32 / 36 in. from 45. For males up to 44
    | the guide prints "35 in. / 88.3 cm"; 35 inches is 88.9 cm, which is what is used here.
    */
    'waist' => [
        'overweight_below_bmi' => 30,
        'cutoffs' => [
            ['max_age' => 44, 'female' => 78.7, 'male' => 88.9],
            ['max_age' => null, 'female' => 81.3, 'male' => 91.4],
        ],
    ],

];
