import { useState } from "react";
import { useNavigate } from "react-router-dom";

import {
  ArrowLeft,
  CheckCircle,
  Eye,
  EyeOff,
  LockKeyhole,
  LogIn,
  Mail,
  ShieldCheck,
  User,
} from "lucide-react";

import { useAuth } from "../../context/AuthContext";
import ThemeToggle from "../../components/ThemeToggle";
import api from "../../services/api";

export default function Login() {
  // Forgot password mode
  const [forgotMode, setForgotMode] = useState(false);

  const [showPassword, setShowPassword] = useState(false);

  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  const [busy, setBusy] = useState(false);

  const [loginForm, setLoginForm] = useState({
    login: "",
    password: "",
  });

  // Forgot password form
  const [forgotEmail, setForgotEmail] = useState("");

  const { login } = useAuth();

  const navigate = useNavigate();

  // ==========================================
  // SWITCH BETWEEN LOGIN / FORGOT
  // ==========================================

  // Open forgot password form
  const openForgotPassword = () => {
    setForgotMode(true);

    setError("");
    setMessage("");
    setForgotEmail("");
  };

  // Return to login
  const backToLogin = () => {
    setForgotMode(false);

    setError("");
    setMessage("");
  };

  // ==========================================
  // LOGIN
  // ==========================================

  const submitLogin = async (e) => {
    e.preventDefault();

    if (busy) return;

    setError("");
    setMessage("");
    setBusy(true);

    try {
      await login(
        loginForm.login,
        loginForm.password
      );

      // Admin-only system: always land on the admin dashboard.
      navigate("/admin", { replace: true });

    } catch (err) {
      setError(
        err.response?.data?.message ||
          "Login failed. Please check your username/email and password."
      );

    } finally {
      setBusy(false);
    }
  };

  // ==========================================
  // FORGOT PASSWORD
  // ==========================================

  const submitForgotPassword = async (e) => {
    e.preventDefault();

    if (busy) return;

    setError("");
    setMessage("");

    // Basic email validation
    if (!forgotEmail.trim()) {
      setError("Please enter your registered email address.");
      return;
    }

    setBusy(true);

    try {
      const { data } = await api.post('/forgot-password', {
        email: forgotEmail.trim(),
      });

      setMessage(
        data.message ||
          "If your email is registered, a password reset link has been sent."
      );

      setForgotEmail("");

    } catch (err) {
      setError(
        err.response?.data?.message ||
          err.message ||
          "Unable to send password reset link. Please try again."
      );

    } finally {
      setBusy(false);
    }
  };

  // ==========================================
  // INPUT HANDLER
  // ==========================================

  const updateLogin = (e) =>
    setLoginForm((current) => ({
      ...current,
      [e.target.name]: e.target.value,
    }));

  // ==========================================
  // UI
  // ==========================================

  return (
    <main className="auth-page">
      <ThemeToggle />
      <section className="auth-shell">

        {/* ======================================
            LEFT SIDE — brand panel
        ====================================== */}

        <aside className="auth-intro">

          <div className="auth-brand-row">

            <div className="auth-logo">
              <img src="/logo.png" alt="Trinidad Municipal College" />
            </div>

            <div>
              <strong>
                Trinidad Municipal College
              </strong>

              <span>
                Equipment Services
              </span>
            </div>

          </div>

          <div className="auth-intro-content">

            <span className="auth-kicker">
              Equipment Management
            </span>

            <h1>
              Borrow.
              <br />
              Track.
              <br />
              Return.
            </h1>

            <p>
              Requests, returns, and records —
              all in one place.
            </p>

            <div className="auth-feature-list">

              <div>
                <ShieldCheck size={17} />
                <span>Secure</span>
              </div>

              <div>
                <LockKeyhole size={17} />
                <span>Private</span>
              </div>

              <div>
                <CheckCircle size={17} />
                <span>Tracked</span>
              </div>

            </div>

          </div>

          <div className="auth-intro-footer">

            <span>
              © {new Date().getFullYear()} Trinidad Municipal College
            </span>

          </div>

        </aside>

        {/* ======================================
            RIGHT SIDE — form
        ====================================== */}

        <section className="auth-form-area">

          <div className="auth-form-card">

            {/* ==================================
                FORGOT PASSWORD
            ================================== */}

            {forgotMode ? (

              <>

                <div className="auth-form-heading">

                  <h2>
                    Reset Password
                  </h2>

                  <p>
                    Enter your email and we'll
                    send a reset link.
                  </p>

                </div>

                {error && (
                  <div className="alert error">
                    {error}
                  </div>
                )}

                {message && (
                  <div className="alert success">
                    <CheckCircle size={18} />
                    {message}
                  </div>
                )}

                <form
                  onSubmit={submitForgotPassword}
                  className="auth-form"
                >

                  <div className="auth-field">

                    <label htmlFor="auth-forgot-email">
                      Registered Email
                    </label>

                    <div className="input-with-icon">

                      <Mail size={18} />

                      <input
                        id="auth-forgot-email"
                        type="email"
                        name="forgotEmail"
                        value={forgotEmail}
                        onChange={(e) =>
                          setForgotEmail(e.target.value)
                        }
                        placeholder="Enter your registered email"
                        autoComplete="email"
                        required
                      />

                    </div>

                  </div>

                  <button
                    className="btn btn-primary auth-submit"
                    type="submit"
                    disabled={busy}
                  >

                    <Mail size={18} />

                    {busy
                      ? "Sending…"
                      : "Send Reset Link"}

                  </button>

                  <div className="auth-switch">

                    <button
                      type="button"
                      onClick={backToLogin}
                      className="text-button"
                    >

                      <ArrowLeft size={16} />

                      Back to Login

                    </button>

                  </div>

                </form>

              </>

            ) : (

              /* ==================================
                 LOGIN
              ================================== */

              <>

                <div className="auth-form-heading">

                  <h2>
                    Welcome Back
                  </h2>

                  <p>
                    Sign in to continue to the
                    admin dashboard.
                  </p>

                </div>

                {error && (
                  <div className="alert error">
                    {error}
                  </div>
                )}

                {message && (
                  <div className="alert success">
                    {message}
                  </div>
                )}

                <form
                  onSubmit={submitLogin}
                  className="auth-form"
                >

                  <div className="auth-field">

                    <label htmlFor="auth-login">
                      Username or Email
                    </label>

                    <div className="input-with-icon">

                      <User size={18} />

                      <input
                        id="auth-login"
                        name="login"
                        value={loginForm.login}
                        onChange={updateLogin}
                        placeholder="Enter username or email"
                        autoComplete="username"
                        required
                      />

                    </div>

                  </div>

                  <div className="auth-field">

                    <div className="field-head">

                      <label htmlFor="auth-password">
                        Password
                      </label>

                      <button
                        type="button"
                        className="text-button auth-inline-link"
                        onClick={openForgotPassword}
                      >
                        Forgot password?
                      </button>

                    </div>

                    <div className="password-field input-with-icon">

                      <LockKeyhole size={18} />

                      <input
                        id="auth-password"
                        type={
                          showPassword
                            ? "text"
                            : "password"
                        }
                        name="password"
                        value={loginForm.password}
                        onChange={updateLogin}
                        placeholder="Enter your password"
                        autoComplete="current-password"
                        required
                      />

                      <button
                        type="button"
                        className="password-toggle"
                        onClick={() =>
                          setShowPassword(
                            (value) => !value
                          )
                        }
                        aria-label={
                          showPassword
                            ? "Hide password"
                            : "Show password"
                        }
                      >

                        {showPassword ? (
                          <EyeOff size={18} />
                        ) : (
                          <Eye size={18} />
                        )}

                      </button>

                    </div>

                  </div>

                  <button
                    className="btn btn-primary auth-submit"
                    type="submit"
                    disabled={busy}
                  >

                    <LogIn size={18} />

                    {busy
                      ? "Signing In…"
                      : "Sign In"}

                  </button>

                  <p className="auth-form-note">

                    <ShieldCheck size={15} />

                    Authorized personnel only

                  </p>

                </form>

              </>

            )}

          </div>

        </section>

      </section>

    </main>
  );
}
