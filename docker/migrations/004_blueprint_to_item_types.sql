-- Migration 004: add blueprint_path column to item_types
-- Blueprints are stored per item type; all items of the same type share one blueprint.

ALTER TABLE item_types
    ADD COLUMN blueprint_path VARCHAR(255) NULL AFTER name;
