import { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import api from "../api/axios";

export default function MyPosts() {
  // store the list of my posts
  const [posts, setPosts] = useState([]);

  // store loading and error states
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // fetch my posts when page loads
  useEffect(() => {
    fetchMyPosts();
  }, []);

  const fetchMyPosts = async () => {
    setLoading(true);
    setError(null);

    try {
      // get current user info
      const userRes = await api.get("/me");
      const userId = userRes.data.id;

      // get all posts then filter by current user
      const postsRes = await api.get("/posts");
      const allPosts = postsRes.data.data;

      // keep only posts that belong to current user
      const myPosts = allPosts.filter((post) => post.author?.id === userId);

      setPosts(myPosts);
    } catch (err) {
      setError("Failed to load your posts. Please login first.");
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (postId) => {
    // ask user to confirm before deleting
    const confirmed = window.confirm(
      "Are you sure you want to delete this post?",
    );

    if (!confirmed) return;

    try {
      // call DELETE /api/posts/{id}
      await api.delete(`/posts/${postId}`);

      // remove deleted post from list without reloading
      setPosts(posts.filter((post) => post.id !== postId));
    } catch (err) {
      alert("Failed to delete post.");
    }
  };

  if (loading) return <p>Loading...</p>;
  if (error) return <p style={{ color: "red" }}>{error}</p>;

  return (
    <div>
      <h1>My Posts</h1>

      {posts.length === 0 ? (
        <p>
          You have no posts yet. <Link to="/create-post">Create one!</Link>
        </p>
      ) : (
        posts.map((post) => (
          <div key={post.id}>
            <h2>
              <Link to={`/posts/${post.id}`}>{post.title}</Link>
            </h2>
            <p>Status: {post.status}</p>
            <p>{post.excerpt}</p>

            <a href={`/posts/${post.id}/edit`}>Edit</a>
            {" | "}
            <button onClick={() => handleDelete(post.id)}>Delete</button>
            <hr />
          </div>
        ))
      )}
    </div>
  );
}
