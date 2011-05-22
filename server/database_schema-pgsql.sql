--
-- Database: crashlogs_main
--

-- --------------------------------------------------------

--
-- Table structure for table apps
--

-- contains a list of all applications that are accepted
-- bundleidentifier: the bundle identifier of the application allowed to provide crash reports
-- symbolicate: if the todo table should be filled to remotely symbolicate crash reports for this applciation

SET AUTOCOMMIT TO OFF;

BEGIN;

CREATE SEQUENCE "apps_id_seq" ;

CREATE TABLE  "apps" (
   "id" integer DEFAULT nextval('"apps_id_seq"') NOT NULL,
   "bundleidentifier"   varchar(250) NOT NULL, 
   "name"   varchar(50) NOT NULL, 
   "symbolicate"    smallint default '0', 
   "issuetrackerurl"   text default NULL, 
   "notifyemail"   text default NULL, 
   "notifypush"   text default NULL, 
   "hockeyappidentifier"   text default NULL, 
   primary key ("id")
)   ;
CREATE INDEX "apps_symbolicate_idx" ON "apps" USING btree ("symbolicate");

-- --------------------------------------------------------

--
-- Table structure for table crash
--

-- contains all crash log data
-- userid: if there was some kind of user/device identification provided in the crash log, this contains the string provided
-- contact: if there was some kind of contact information provided, this contains the string
-- systemversion: the version of the operation system running when this crash was sent (!!), could be different to the version the crash happened
-- bundleidentifier: the bundle identifier of the application this crash report is associated with
-- serverversion: the version of the app that sent this report
-- version: the version of the app that crashed
-- description: if there was some description text provided, this contains the string
-- log: the actual crash log data
-- timestamp: the timestamp the crash log data was added to the database
-- groupid: the crash group this crash was associated with

CREATE SEQUENCE "crash_id_seq" ;

CREATE TABLE  "crash" (
   "id" integer DEFAULT nextval('"crash_id_seq"') NOT NULL,
   "userid"   varchar(255) default NULL, 
   "contact"   varchar(255) default NULL, 
   "systemversion"   varchar(25) default NULL, 
   "platform"   varchar(25) default NULL, 
   "bundleidentifier"   varchar(250) default NULL, 
   "applicationname"   varchar(50) default NULL, 
   "senderversion"   varchar(15) NOT NULL default '', 
   "version"   varchar(15) default NULL, 
   "description"   text, 
   "log"   text NOT NULL, 
   "timestamp"   timestamp NOT NULL default CURRENT_TIMESTAMP, 
   "groupid" bigint CHECK ("groupid" >= 0) default '0',
   "jailbreak" int CHECK ("jailbreak" >= 0) default '0',
   primary key ("id")
);
CREATE INDEX "crash_jailbreak_idx" ON "crash" USING btree ("jailbreak");
CREATE INDEX "crash_timestamp_idx" ON "crash" USING btree ("timestamp");
CREATE INDEX "crash_applicationname_idx" ON "crash" USING btree ("applicationname");
CREATE INDEX "crash_userid_idx" ON "crash" USING btree ("userid");
CREATE INDEX "crash_version_idx" ON "crash" USING btree ("version");
CREATE INDEX "crash_platform_idx" ON "crash" USING btree ("platform");
CREATE INDEX "crash_senderversion_idx" ON "crash" USING btree ("senderversion");
CREATE INDEX "crash_contact_idx" ON "crash" USING btree ("contact");
CREATE INDEX "crash_systemversion_idx" ON "crash" USING btree ("systemversion");
CREATE INDEX "crash_bundleidentifier_idx" ON "crash" USING btree ("bundleidentifier");

-- --------------------------------------------------------

--
-- Table structure for table crash_groups
--

-- contains a list of groups for similar crashes
-- bundleidentifier: the bundle identifier that this crash group is associated with
-- affected: the version of the application that has this crash
-- fix: the version which will fix this crash
-- pattern: the string to search for to detect if a crash belongs to this group
-- description: an optional description text which can be added in the admin UI
-- amoun: the amount crash logs associated with this crash group

CREATE SEQUENCE "crash_groups_id_seq" ;

CREATE TABLE "crash_groups" (
   "id" integer DEFAULT nextval('"crash_groups_id_seq"') NOT NULL,
   "bundleidentifier"   varchar(250) default NULL, 
   "affected"   varchar(20) default NULL, 
   "fix"   varchar(20) default NULL, 
   "pattern"   varchar(250) NOT NULL default '', 
   "description"   text, 
   "amount"   bigint default '0', 
   "latesttimestamp"   bigint default '0', 
   primary key ("id")
)    ;
CREATE INDEX "crash_groups_1_idx" ON "crash_groups" USING btree ("affected", "fix");
CREATE INDEX "crash_groups_bundleidentifier_idx" ON "crash_groups" USING btree ("bundleidentifier");
CREATE INDEX "crash_groups_pattern_idx" ON "crash_groups" USING btree ("pattern");
CREATE INDEX "crash_groups_amount_idx" ON "crash_groups" USING btree ("amount");
CREATE INDEX "crash_groups_latesttimestamp_idx" ON "crash_groups" USING btree ("latesttimestamp");

-- --------------------------------------------------------

--
-- Table structure for table symbolicated
--

-- contains a todo list for crashes that need to be symbolicated by a remote task
-- crashid: the id of the crash log data to symbolicate
-- done: value of 1 if symbolification is completed, 0 if to be done

CREATE SEQUENCE "symbolicated_id_seq" ;

CREATE TABLE  "symbolicated" (
   "id" integer DEFAULT nextval('"symbolicated_id_seq"') NOT NULL,
   "crashid" bigint CHECK ("crashid" >= 0) NOT NULL default '0',
   "done"   int NOT NULL default '0', 
   primary key ("id")
)   ;
CREATE INDEX "symbolicated_crashid_idx" ON "symbolicated" USING btree ("crashid");
CREATE INDEX "symbolicated_done_idx" ON "symbolicated" USING btree ("done");

-- --------------------------------------------------------

--
-- Table structure for table versions
--

-- contains a list of versions for a specific application
-- bundleidentifier: the application this versions belongs to
-- version: the version number as a string
-- status: the status of this version, see config.php for values

CREATE SEQUENCE "versions_id_seq" ;

CREATE TABLE  "versions" (
   "id" integer DEFAULT nextval('"versions_id_seq"') NOT NULL,
   "bundleidentifier"   varchar(250) default NULL, 
   "version"   varchar(20) default NULL, 
   "status"   int NOT NULL default '0', 
   "notify"   int NOT NULL default '0', 
   primary key ("id")
)    ;
CREATE INDEX "versions_1_idx" ON "versions" USING btree ("version", "status");
CREATE INDEX "versions_bundleidentifier_idx" ON "versions" USING btree ("bundleidentifier");

COMMIT;
