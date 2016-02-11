<?php
return array(
    "PRODUCTION" => array(
        "LMS_HOST"   => "https://www.yesnyoulearning.com",
        "LMS_URL"    => "https://www.yesnyoulearning.com/api", // MUST NOT have trailing /
        "LMS_SECRET" => "bE%foALNF0Xwqa*9SwQteSwsyHkZTU_*07#i",
        "LMS_KEY"    => "3KCJNd4iJVi*h#-G6K_r%EB-",
    ),
    "SANDBOX"    => array(
        "LMS_HOST"   => "https://yesnyou1.docebosandbox.com",
        "LMS_URL"    => "http://yesnyou1.docebosandbox.com/api", // MUST NOT have trailing /
        "LMS_SECRET" => "Kmma76qs90qFAraQP91Hl+!58_f8XcOB+g_f",
        "LMS_KEY"    => "kw8wHMAlIudZh1SV9Bj*bwMi",
    ),
    /*
    --- Branch hierarchy definitions ---
    [owner_division] => HQ
    [owner_department] => Sales;
    [owner_country] => France;
    [owner_city] => Lyon;
    */
    "BRANCHES"   => array(
        "FRANCE"  => array(
            "PARIS"           => "YNY_FR_PARIS",
            "LYON"            => "YNY_FR_LYON",
            "AIX-EN-PROVENCE" => "YNY_FR_AIX",
            "LILLE"           => "YNY_FR_LILLE",
            "LENS"            => "YNY_FR_LENS",
            "VALENCIENNES"    => "YNY_FR_VALENCIENNES",
            "RENNES"          => "YNY_FR_RENNES",
        ),
        "GERMANY" => array(),
        "SPAIN"   => array()
    ),
    /*
    --- Learning plan Salesforce CODE|LEVEL -> LMS Learning plan NAME mappings
    */
    "LP"         => array(
        "01t200000038Zmd|"     => "LSAT",
        "01t200000038avr|A1.1" => "BUSINESS SEMINAR 3 DAYS A1.3",
        "01t200000038avr|A1.2" => "BUSINESS SEMINAR 3 DAYS A1.2",
        "01t200000038avr|A1.3" => "BUSINESS SEMINAR 3 DAYS A1.3",
        "01t200000038avr|A1.4" => "BUSINESS SEMINAR 3 DAYS A1.4",
        "01t200000038avr|B2.1" => "BUSINESS SEMINAR 3 DAYS B2.1",
        "01t200000039aOR"      => "BUSINESS SEMINAR 1 DAY",
        "01t200000038arq"      => "BLENDED IMPACT 20H VC"
    ),
    "GENDER"     => array(
        "Male"   => "Man",
        "Female" => "Woman"
    ),
    "PM"         => array(
        "anaïs establie"       => "Anaïs Establie",
        "anaïs tourlourat"     => "Anaïs Tourlourat",
        "célia schubert"       => "Célia Schubert",
        "jad khaddage"         => "Jad Khaddage",
        "jean-charles marguin" => "Jean-Charles Marguin",
        "joëlle nogaret"       => "Joëlle Nogaret",
        "jonathan hababou"     => "Jonathan Hababou",
        "nesrine feraga"       => "Nesrine Feraga",
        "pascal mazabraud"     => "Pascal Mazabraud",
        "patrick gozal"        => "Patrick Gozal",
        "stylianos antalis"    => "Stylianos Antalis",
        "yny hq"               => "Stylianos Antalis",
    )
);
