CREATE TABLE Person (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed

  surname       VARCHAR(255) NULL,              # Name of the Person
  forename      VARCHAR(255) NULL,              # Firstname(s)

  preferredName VARCHAR(511) NULL,
  variantNames  VARCHAR(1023) NULL,

  gender        ENUM('female', 'male') NULL,

  gnd           VARCHAR(10) NULL,               #
  viaf          VARCHAR(16) NULL,               #
  lc_naf        VARCHAR(16) NULL,               #

  created_at    DATETIME,                       # when it was created
  created_by    INT NULL,                       # ref to Logins.id: who created the entry
  changed_at    DATETIME NULL,                  # when it was changed
  changed_by    INT NULL                        #
) CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE Publisher (
  id            INT AUTO_INCREMENT PRIMARY KEY, # unique id
  status        INT NOT NULL DEFAULT 0,         # -1: removed

  preferredName VARCHAR(511) NULL,
  variantNames  VARCHAR(1023) NULL,
  places        VARCHAR(1023) NULL,

  created_at    DATETIME,                       # when it was created
  created_by    INT NULL,                       # ref to Logins.id: who created the entry
  changed_at    DATETIME NULL,                  # when it was changed
  changed_by    INT NULL                        #
) CHARACTER SET utf8 COLLATE utf8_unicode_ci;
