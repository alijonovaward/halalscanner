Feature: Evidence and halal certificate verification
  Halal claims must be traceable to verified evidence.
  A package label or user report is not enough to produce halal.

  Background:
    Given the user's madhhab is "Hanafi"

  Scenario: Halal label without certificate is not enough
    Given a product package says "halal"
    And no certificate can be verified
    When the product evaluates the package claim
    Then the halal verdict is "mushbooh"
    And the reason code is "certificate_missing"
    And the explanation says the halal claim is not verified
    And the result does not claim halal

  Scenario: Certificate from untrusted body is cautious
    Given a product has a halal certificate
    And the certification body is not in the trusted body list
    When the product evaluates the certificate
    Then the halal verdict is "mushbooh"
    And the reason code is "certificate_untrusted"
    And the explanation names the untrusted or unknown certification body

  Scenario: Trusted certificate can support halal when no conflicts exist
    Given a product has a valid halal certificate
    And the certification body is trusted
    And the certificate covers the exact product and manufacturer
    And there are no conflicting ingredient or source rules
    When the product evaluates the certificate
    Then the halal verdict is "halal"
    And the result includes certificate source, issuing body, and verification date

  Scenario: User submission cannot become authoritative without review
    Given a user reports that a product is halal
    And no trusted source or editor review confirms the report
    And the product has no previous verified halal verdict
    When the product evaluates the report
    Then the report is stored as a pending data contribution
    And the halal verdict remains "unknown"
