
CREATE TABLE sections (
 id int primary key,
 section_number int,
 title text,
 parent_id int, -- refers to to audiobooks primary key
 author text,
 reader_name text,
 reader_id int
);

CREATE TABLE audiobooks (

 id integer primary key,
 title text,
 description text,
 language text,
 copyright_year int,
 num_sections int,
 url_rss text,
 url_zip_file text,
 url_project text,
 url_librivox text,
 url_iarchive text,
 url_other text,
 totaltime text, 
 totaltimesecs int,
 authors text,  -- comma separated ids
 sections text, -- comma separated ids
 genres text,
 publicdate text,
 downloads int

);

CREATE TABLE authors (
 id integer primary key,
 first_name text,
 last_name text,
 dob int,
 dod int
);

