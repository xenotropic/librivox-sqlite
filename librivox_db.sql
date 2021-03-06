
CREATE TABLE sections (
 id int primary key,
 section_number int,
 title text collate nocase,
 parent_id int, -- refers to to audiobooks primary key
 author text collate nocase,
 reader_name text,
 reader_id int,
 language text
);

CREATE TABLE audiobooks (

 id integer primary key,
 title text collate nocase,
 description text collate nocase,
 language text collate nocase,
 copyright_year int,
 num_sections int,
 url_text_source text collate nocase,
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
 downloads int, 
 etext_id int  -- gutenberg id derived from url_text_source where applicable 
 
);

CREATE TABLE authors (
 id integer primary key,
 first_name text,
 last_name text,
 dob int,
 dod int
);

CREATE INDEX audiobooks_title_index on audiobooks ( title collate nocase );
CREATE INDEX sections_title_index on sections ( title collate nocase );
CREATE INDEX sections_author_index on sections ( author collate nocase );
CREATE INDEX parent_id_index on sections ( parent_id );
CREATE INDEX author_lastname on authors ( last_name collate nocase);


