Feature: Makruh and madhhab-sensitive verdicts
  Makruh is stored as an internal fiqh classification, not as a fifth public status.
  The user sees a clear public verdict plus a cautious explanation.

  Background:
    Given the user is in the MVP region "Uzbekistan"

  Scenario: Makruh tanzihi is shown as a caution, not haram
    Given the user's madhhab is "Hanafi"
    And an ingredient rule has fiqh classification "makruh_tanzihi"
    And the ingredient otherwise has sufficient halal evidence
    And there is no stronger haram evidence
    When the product evaluates the ingredient
    Then the halal verdict is "halal"
    And the result includes reason code "makruh_tanzihi"
    And the explanation says the item is discouraged or better avoided in the selected madhhab

  Scenario: Makruh tahrimi is not silently downgraded to halal
    Given the user's madhhab is "Hanafi"
    And an ingredient rule has fiqh classification "makruh_tahrimi"
    And there is no separate expert-approved haram rule
    When the product evaluates the ingredient
    Then the halal verdict is "mushbooh"
    And the result includes reason code "makruh_tahrimi"
    And the explanation says the item is strongly discouraged in the selected madhhab
    And the result does not claim confident haram

  Scenario: One ingredient can differ by madhhab
    Given an ingredient has different rules for Hanafi and Shafi'i
    And the Hanafi rule returns "mushbooh"
    And the Shafi'i rule returns "halal"
    And the user's madhhab is "Hanafi"
    When the product evaluates the ingredient
    Then the halal verdict is "mushbooh"
    And the result includes reason code "madhhab_disagreement"
    And the explanation says other madhhabs may evaluate it differently

  Scenario: Changing madhhab recalculates the verdict
    Given the user previously evaluated a product as "Hanafi"
    When the user changes the madhhab to "Maliki"
    Then the product recalculates the halal verdict
    And the result shows the active madhhab used for evaluation
    And the previous Hanafi explanation is not reused as the Maliki explanation
