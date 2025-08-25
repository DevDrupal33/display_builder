import { test as base } from '@playwright/test'
import { Displaybuilder } from '../objects/DisplayBuilder'

type DisplayBuilderObj = {
  displayBuilder: Displaybuilder
}

export const displayBuilder = base.extend<DisplayBuilderObj>({
  displayBuilder: [
    async ({ page }, use) => {
      const displayBuilder = new Displaybuilder({ page })
      await use(displayBuilder)
    },
    { auto: true },
  ],
})
