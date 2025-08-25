import { mergeTests } from '@playwright/test'
import { drupalSite, drupal, beforeAllTests, beforeEachTest } from './DrupalSite'
import { displayBuilder } from './DisplayBuilder'

export const test = mergeTests(drupalSite, drupal, displayBuilder, beforeAllTests, beforeEachTest)
