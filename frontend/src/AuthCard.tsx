import { useEffect, useState } from 'react'
import { currentUser, logout } from './auth'
import type { CurrentUser } from './auth'

type SessionStatus = 'loading' | 'guest' | 'authenticated' | 'error'

function signInMessage(): string | null {
  const error = new URLSearchParams(window.location.search).get('auth_error')
  switch (error) {
    case null: return null
    case 'cancelled': return 'Sign-in was cancelled. You can try again when you’re ready.'
    case 'unavailable': return 'Google sign-in is currently unavailable. Please try again later.'
    case 'account_conflict': return 'We couldn’t sign you in with this Google account. Please contact the app owner.'
    default: return 'We couldn’t complete Google sign-in. Please try again.'
  }
}

export default function AuthCard() {
  const [status, setStatus] = useState<SessionStatus>('loading')
  const [user, setUser] = useState<CurrentUser | null>(null)
  const [message, setMessage] = useState(signInMessage)
  const [attempt, setAttempt] = useState(0)
  const [signingOut, setSigningOut] = useState(false)

  useEffect(() => {
    const url = new URL(window.location.href)
    if (url.searchParams.has('auth_error')) {
      url.searchParams.delete('auth_error')
      window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`)
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    const timeout = window.setTimeout(() => controller.abort(), 10_000)
    let cancelled = false

    void currentUser(controller.signal).then((account) => {
      if (cancelled) return
      setUser(account)
      setStatus(account ? 'authenticated' : 'guest')
    }).catch(() => {
      if (!cancelled) setStatus('error')
    }).finally(() => window.clearTimeout(timeout))

    return () => {
      cancelled = true
      window.clearTimeout(timeout)
      controller.abort()
    }
  }, [attempt])

  function retry() {
    setStatus('loading')
    setAttempt((value) => value + 1)
  }

  async function signOut() {
    setSigningOut(true)
    setMessage(null)
    try {
      await logout()
      setUser(null)
      setStatus('guest')
      setMessage('You have signed out.')
    } catch {
      setMessage('We couldn’t sign you out. Please try again.')
    } finally {
      setSigningOut(false)
    }
  }

  return (
    <section className="auth-card" aria-labelledby="auth-heading" aria-busy={status === 'loading' || signingOut}>
      <div role="status" aria-live="polite" aria-atomic="true">
        <p className="eyebrow">Your account</p>
        <h2 id="auth-heading">{status === 'authenticated' && user ? `Welcome, ${user.name}` : 'Keep your care together.'}</h2>
        {status === 'loading' && <p>Checking your session…</p>}
        {status === 'guest' && <p>Sign in with your Google account to get started.</p>}
        {status === 'authenticated' && user && <p className="account-email">{user.email}</p>}
        {status === 'error' && <p>We couldn’t check your session. Please try again.</p>}
        {message && <p className="auth-message">{message}</p>}
      </div>
      {status === 'guest' && <a className="google-sign-in" href="/auth/google/redirect">Sign in with Google</a>}
      {status === 'authenticated' && <button type="button" onClick={() => void signOut()} disabled={signingOut}>
        {signingOut ? 'Signing out…' : 'Sign out'}
      </button>}
      {status === 'error' && <button type="button" onClick={retry}>Try again</button>}
    </section>
  )
}
