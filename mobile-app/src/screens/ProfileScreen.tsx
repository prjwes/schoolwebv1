"use client"

import { useState, useEffect } from "react"
import { View, Text, ScrollView, StyleSheet, TouchableOpacity, Image, ActivityIndicator, Alert } from "react-native"
import * as SecureStore from "expo-secure-store"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"
import axios from "axios"

const API_URL = "https://schoolweb.ct.ws"

export default function ProfileScreen({ navigation }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchUserProfile()
  }, [])

  const fetchUserProfile = async () => {
    try {
      const userId = await SecureStore.getItemAsync("userId")
      const token = await SecureStore.getItemAsync("userToken")
      const userName = await SecureStore.getItemAsync("userName")

      const response = await axios.get(`${API_URL}/api/user/profile.php?user_id=${userId}`, {
        headers: { Authorization: `Bearer ${token}` },
      })

      if (response.data.success) {
        setUser(response.data.user)
      }
    } catch (error) {
      console.error("Error fetching profile:", error)
    } finally {
      setLoading(false)
    }
  }

  const handleLogout = async () => {
    Alert.alert("Logout", "Are you sure you want to logout?", [
      { text: "Cancel", onPress: () => {} },
      {
        text: "Logout",
        onPress: async () => {
          await SecureStore.deleteItemAsync("userToken")
          await SecureStore.deleteItemAsync("userRole")
          await SecureStore.deleteItemAsync("userId")
          navigation.reset({
            index: 0,
            routes: [{ name: "Login" }],
          })
        },
      },
    ])
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#0066cc" />
      </View>
    )
  }

  return (
    <ScrollView style={styles.container}>
      {user && (
        <>
          <View style={styles.header}>
            <View style={styles.avatarContainer}>
              {user.profile_image ? (
                <Image source={{ uri: `${API_URL}/${user.profile_image}` }} style={styles.avatar} />
              ) : (
                <View style={[styles.avatar, styles.avatarPlaceholder]}>
                  <MaterialIcons name="person" size={50} color="#fff" />
                </View>
              )}
            </View>
            <Text style={styles.name}>{user.full_name}</Text>
            <Text style={styles.role}>{user.role}</Text>
          </View>

          <View style={styles.infoSection}>
            <Text style={styles.sectionTitle}>Contact Information</Text>
            <View style={styles.infoCard}>
              <View style={styles.infoRow}>
                <MaterialIcons name="email" size={20} color="#0066cc" />
                <Text style={styles.infoText}>{user.email || "N/A"}</Text>
              </View>
              <View style={styles.infoRow}>
                <MaterialIcons name="person" size={20} color="#0066cc" />
                <Text style={styles.infoText}>{user.username || "N/A"}</Text>
              </View>
            </View>
          </View>

          <View style={styles.section}>
            <TouchableOpacity style={[styles.button, styles.logoutButton]} onPress={handleLogout}>
              <MaterialIcons name="logout" size={20} color="#fff" />
              <Text style={styles.buttonText}>Logout</Text>
            </TouchableOpacity>
          </View>
        </>
      )}
    </ScrollView>
  )
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f5f5f5",
  },
  centerContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  header: {
    backgroundColor: "#0066cc",
    padding: 30,
    alignItems: "center",
    paddingTop: 40,
  },
  avatarContainer: {
    marginBottom: 15,
  },
  avatar: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: "#fff",
  },
  avatarPlaceholder: {
    justifyContent: "center",
    alignItems: "center",
    backgroundColor: "#0052a3",
  },
  name: {
    fontSize: 24,
    fontWeight: "bold",
    color: "#fff",
    marginBottom: 5,
  },
  role: {
    fontSize: 14,
    color: "#e0e0e0",
  },
  infoSection: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: "600",
    color: "#333",
    marginBottom: 12,
  },
  infoCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    overflow: "hidden",
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 3,
  },
  infoRow: {
    flexDirection: "row",
    alignItems: "center",
    padding: 15,
    borderBottomWidth: 1,
    borderBottomColor: "#eee",
  },
  infoText: {
    fontSize: 14,
    color: "#333",
    marginLeft: 12,
    flex: 1,
  },
  section: {
    padding: 20,
  },
  button: {
    flexDirection: "row",
    paddingVertical: 14,
    paddingHorizontal: 20,
    borderRadius: 8,
    alignItems: "center",
    justifyContent: "center",
  },
  logoutButton: {
    backgroundColor: "#dc3545",
  },
  buttonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
    marginLeft: 10,
  },
})
