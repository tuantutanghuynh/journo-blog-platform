import { useState, useEffect } from "react";
import api from "../api/axios";

export default function Home() {
  // store the list of posts
  const [posts, setPosts] = useState([]);

  // store loading state - true while fetching data
  const [loading, setLoading] = useState(true);

  // store error message if something goes wrong
  const [error, setError] = useState(null);

  // fetch posts when the component first loads
  useEffect(() => {
    fetchPosts();
  }, []); // [] means run only once when component mounts

  const fetchPosts = async () => {
    // start loading
    setLoading(true);

    // reset error
    setError(null);

    try {
      // call GET /api/posts
      const res = await api.get("/posts");

      // save posts to state
      // res.data.data because Laravel paginate() wraps posts in { data: [...] }
      setPosts(res.data.data);

    } catch (err) {
      // show error message if fetch fails
      setError("Failed to load posts");

    } finally {
      // always stop loading whether success or fail
      setLoading(false);
    }
  };

  // show loading message while fetching
  if (loading) {
    return <p>Loading posts...</p>;
  }

  // show error message if fetch failed
  if (error) {
    return <p style={{ color: "red" }}>{error}</p>;
  }

  return (
    <div>
      <h1>All Posts</h1>

      {posts.length === 0 ? (
        <p>No posts yet</p>
      ) : (
        posts.map((post) => (
          <div key={post.id}>
            <h2>{post.title}</h2>
            <p>{post.excerpt}</p>
            <p>By: {post.author?.name}</p>
            <hr />
          </div>
        ))
      )}
    </div>
  );
}
