/**
 * @file
 * Utility functions for Playwright tests.
 */

/**
 * Return a string of random characters of specified length.
 *
 * @param {number} length
 *   Length of string to return.
 * @returns {string}
 *   The random string.
 */
export function createRandomString(length: number = 3): string {
  let result = ''
  const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
  const charactersLength = characters.length

  for (let i = 0; i < length; i += 1) {
    result += characters.charAt(Math.floor(Math.random() * charactersLength))
  }

  return result
}

/**
 * Log message to console if log level is set to verbose, debug or silly.
 *
 * @param {string} message
 *   The message to log.
 */
export function debug(message: string): void {
  const logMap = {
    error: 0,
    warn: 1,
    info: 2,
    http: 3,
    verbose: 4,
    debug: 5,
    silly: 6
  }
  if ((process.env.PLAYWRIGHT_DEBUG_LEVEL || 'info') in logMap) {
    const level = logMap[process.env.PLAYWRIGHT_DEBUG_LEVEL || 'info']
    if (level >= 4) {
      // eslint-disable-next-line no-console
      console.debug(`debug: ${message}`)
    }
  }
}
