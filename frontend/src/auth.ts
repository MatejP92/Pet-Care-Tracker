export type CurrentUser = {
  id: number
  name: string
  email: string
}

export async function currentUser(signal: AbortSignal): Promise<CurrentUser | null> {
  const response = await fetch('/api/user', {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
    cache: 'no-store',
    signal,
  })

  if (response.status === 401) return null
  if (!response.ok) throw new Error('Unable to check the current session')

  const user: unknown = await response.json()
  if (typeof user !== 'object' || user === null
    || !('id' in user) || typeof user.id !== 'number'
    || !('name' in user) || typeof user.name !== 'string'
    || !('email' in user) || typeof user.email !== 'string') {
    throw new Error('Unexpected session response')
  }

  return { id: user.id, name: user.name, email: user.email }
}

export async function logout(): Promise<void> {
  const signal = AbortSignal.timeout(10_000)
  const csrf = await fetch('/sanctum/csrf-cookie', {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
    cache: 'no-store',
    signal,
  })
  if (!csrf.ok) throw new Error('Unable to prepare sign-out')

  const cookie = document.cookie.split(';').map((part) => part.trim())
    .find((part) => part.startsWith('XSRF-TOKEN='))
  if (!cookie) throw new Error('Missing CSRF cookie')

  const response = await fetch('/auth/logout', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)),
    },
    credentials: 'same-origin',
    cache: 'no-store',
    signal,
  })

  if (!response.ok && response.status !== 401) throw new Error('Unable to sign out')
}
