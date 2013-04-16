ALTER TABLE `stbl_action_log`
	ADD COLUMN `aclSequenceNo` BIGINT NULL DEFAULT NULL AFTER `aclFlagIncomplete`;