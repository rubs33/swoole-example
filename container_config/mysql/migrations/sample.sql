CREATE TABLE books (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  author VARCHAR(255) NOT NULL,

  PRIMARY KEY (id)
);

INSERT INTO books VALUES (null, 'Test 1', 'Author 1'), (null, 'Test 2', 'Author 2');