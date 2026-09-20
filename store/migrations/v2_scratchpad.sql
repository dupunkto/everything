CREATE TABLE IF NOT EXISTS scratchpad (
  id int(11) NOT NULL,
  content text NOT NULL,
  PRIMARY KEY (id)
);

INSERT INTO scratchpad (id, content) VALUES (1, '');
