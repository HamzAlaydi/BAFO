/**
 * `device_name` for Sanctum tokens: "Web · {browser} on {OS}" (SCREENS W04), at most 120 characters.
 * It names the token in the user's session list; it is metadata, not UI copy.
 */
export function webDeviceName(userAgent: string | null | undefined): string {
  const ua = userAgent ?? ''
  const browser = /Edg\//.test(ua)
    ? 'Edge'
    : /OPR\//.test(ua)
      ? 'Opera'
      : /Firefox\//.test(ua)
        ? 'Firefox'
        : /Chrome\//.test(ua)
          ? 'Chrome'
          : /Safari\//.test(ua)
            ? 'Safari'
            : 'Browser'
  const os = /iPhone|iPad|iPod/.test(ua)
    ? 'iOS'
    : /Android/.test(ua)
      ? 'Android'
      : /Mac OS X|Macintosh/.test(ua)
        ? 'macOS'
        : /Windows/.test(ua)
          ? 'Windows'
          : /Linux/.test(ua)
            ? 'Linux'
            : 'Unknown OS'
  return `Web · ${browser} on ${os}`.slice(0, 120)
}

/** The current browser's device name (server side: a generic web name). */
export function currentDeviceName(): string {
  return webDeviceName(typeof navigator === 'undefined' ? null : navigator.userAgent)
}
