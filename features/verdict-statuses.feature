Feature: Public halal verdict statuses
  The product returns only the public MVP statuses halal, haram, mushbooh, or unknown.
  Unknown or weak data must never become halal by default.

  Background:
    Given the user's madhhab is "Hanafi"

  Scenario: Unknown barcode does not receive halal
    Given a barcode is not present in the product database
    When the user scans the barcode
    Then the halal verdict is "unknown"
    And the reason code is "unknown_product"
    And the result suggests checking the ingredients as a fallback
    And the result includes no permissive halal claim

  Scenario: Unreadable ingredient photo returns unknown
    Given the user submits an ingredient photo
    And the OCR confidence is below the public result threshold
    When the product evaluates the ingredients
    Then the halal verdict is "unknown"
    And the reason code is "low_ocr_quality"
    And the explanation says the ingredients could not be read reliably

  Scenario: Known haram ingredient blocks halal
    Given a product ingredient is confirmed as pork-derived
    And the source is trusted
    When the product evaluates the ingredients
    Then the halal verdict is "haram"
    And the explanation names the ingredient that caused the verdict
    And the result includes the source and last verification date

  Scenario: Conflicting sources produce a cautious result
    Given one trusted source says an ingredient is halal
    And another trusted source says the same ingredient is haram
    When the product evaluates the ingredients
    Then the halal verdict is "mushbooh"
    And the reason code is "source_conflict"
    And the explanation describes the conflict
    And the result does not claim halal
