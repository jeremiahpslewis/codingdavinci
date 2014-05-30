CREATE TABLE Person (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed

  surname       VARCHAR(255) NULL,              # Name of the Person
  forename      VARCHAR(255) NULL,              # Firstname(s)

  preferred_name VARCHAR(511) NULL,
  variant_names  VARCHAR(1023) NULL,

  gender        ENUM('female', 'male') NULL,
  academic_degree VARCHAR(255) NULL,
  biographical_or_historical_information VARCHAR(1023) NULL,

  date_of_birth DATE NULL,
  date_of_death DATE NULL,

  place_of_birth VARCHAR(255) NULL,             #
  place_of_death VARCHAR(255) NULL,             #


  gnd           VARCHAR(255) NULL,               #
  viaf          VARCHAR(255) NULL,               #
  lc_naf        VARCHAR(255) NULL,               #
  entityfacts   MEDIUMTEXT NULL,

  created_at    DATETIME,                       # when it was created
  created_by    INT NULL,                       # ref to Logins.id: who created the entry
  changed_at    DATETIME NULL,                  # when it was changed
  changed_by    INT NULL                        #
) CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE Publication (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed

  title VARCHAR(511) NULL,
  other_title_information VARCHAR(511) NULL,
  place_of_publication  VARCHAR(1023) NULL,
  publisher VARCHAR(255) NULL,
  publication_statement VARCHAR(1023) NULL,

  is_part_of VARCHAR(1023) NULL,
  bibliographic_citation VARCHAR(1023) NULL,

  extent VARCHAR(255) NULL,             #
  issued VARCHAR(255) NULL,             #


  gnd           VARCHAR(255) NULL,               #
  oclc          VARCHAR(255) NULL,               #

  created_at    DATETIME,                       # when it was created
  created_by    INT NULL,                       # ref to Logins.id: who created the entry
  changed_at    DATETIME NULL,                  # when it was changed
  changed_by    INT NULL                        #
) CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE Publisher (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed

  preferred_name VARCHAR(511) NULL,
  variant_names  VARCHAR(1023) NULL,
  places        VARCHAR(1023) NULL,

  created_at    DATETIME,                       # when it was created
  created_by    INT NULL,                       # ref to Logins.id: who created the entry
  changed_at    DATETIME NULL,                  # when it was changed
  changed_by    INT NULL                        #
) CHARACTER SET utf8 COLLATE utf8_unicode_ci;
