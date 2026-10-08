Feature: Ingredients, additives, and process evidence
  Ingredient names alone are not always enough.
  Origin, process, transformation, and expert approval can affect the verdict.

  Background:
    Given the user's madhhab is "Hanafi"

  Scenario: Gelatin with unknown origin is mushbooh
    Given a product contains "gelatin"
    And the gelatin origin is unknown
    When the product evaluates the ingredients
    Then the halal verdict is "mushbooh"
    And the reason code is "unknown_origin"
    And the explanation says gelatin origin must be verified

  Scenario: Fish gelatin with trusted evidence can be halal
    Given a product contains "gelatin"
    And the gelatin origin is confirmed as fish
    And the source is trusted
    And the rule is approved for the selected madhhab
    When the product evaluates the ingredients
    Then the halal verdict is "halal"
    And the explanation names the fish gelatin evidence
    And the result includes source and verification date

  Scenario: Istihalah claim without process evidence remains cautious
    Given a product contains an additive that may depend on istihalah
    And the manufacturer claims the substance was transformed
    And no process evidence is available
    When the product evaluates the additive
    Then the halal verdict is "mushbooh"
    And the reason code is "process_unclear"
    And the explanation says the transformation process is not verified

  Scenario: Alcohol as a flavor ingredient is high risk
    Given a food product lists alcohol as a flavor ingredient
    And the source confirms it was intentionally added
    When the product evaluates the ingredients
    Then the halal verdict is "haram"
    And the explanation identifies alcohol as the cause
    And the result includes source and verification date

  Scenario: Trace alcohol requires a separate expert-approved rule
    Given a product may contain trace alcohol from a natural process
    And no expert-approved trace alcohol rule applies
    When the product evaluates the ingredients
    Then the halal verdict is "mushbooh"
    And the reason code is "trace_amount_unclear"
    And the explanation says trace amount and process need verification
