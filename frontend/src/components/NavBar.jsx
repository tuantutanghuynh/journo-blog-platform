import { Link } from "react-router-dom";

export default function Navbar() {
  //check if user is logged in by reading token from localStorage
  const token = localStorage.getItem("token");
  const isLoggedIn = token !== null;

  const handleLogout = () => {
    //remove token from localStorage
    localStorage.removeItem("token");

    //redirect to login page
    window.location.href = "/login";
  };

  return (
    <nav>
      <Link to="/">Journo</Link>

      <div>
        {isLoggedIn ? (
          <>
            <Link to="/create-post">New Post</Link>
            <button onClick={handleLogout}>Logout</button>
          </>
        ) : (
          <>
            <Link to="/login">Login</Link>
            <Link to="/register">Register</Link>
          </>
        )}
      </div>
    </nav>
  );
}
