"use client"

import { useState, useEffect } from "react"
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, RefreshControl, Alert, Image } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"

const API_URL = "https://schoolweb.ct.ws"

export default function Clubs() {
  const [clubs, setClubs] = useState([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  useEffect(() => {
    fetchClubs()
  }, [])

  const fetchClubs = async () => {
    try {
      const userId = await SecureStore.getItemAsync("userId")
      const token = await SecureStore.getItemAsync("userToken")

      const response = await axios.get(`${API_URL}/api/student/clubs.php?user_id=${userId}`, {
        headers: { Authorization: `Bearer ${token}` },
      })

      if (response.data.success) {
        setClubs(response.data.clubs || [])
      }
    } catch (error) {
      console.error("Error fetching clubs:", error)
      Alert.alert("Error", "Failed to load clubs")
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  const onRefresh = () => {
    setRefreshing(true)
    fetchClubs()
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#0066cc" />
      </View>
    )
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
    >
      {clubs.length === 0 ? (
        <View style={styles.emptyContainer}>
          <MaterialIcons name="people" size={50} color="#ddd" />
          <Text style={styles.emptyText}>You haven't joined any clubs yet</Text>
        </View>
      ) : (
        <View style={styles.clubsContainer}>
          {clubs.map((club) => (
            <View key={club.id} style={styles.clubCard}>
              {club.image && <Image source={{ uri: `${API_URL}/${club.image}` }} style={styles.clubImage} />}
              <View style={styles.clubInfo}>
                <Text style={styles.clubName}>{club.name}</Text>
                <Text style={styles.clubDesc} numberOfLines={2}>
                  {club.description}
                </Text>
                <View style={styles.clubFooter}>
                  <View style={styles.memberBadge}>
                    <MaterialIcons name="people" size={14} color="#0066cc" />
                    <Text style={styles.memberCount}>{club.members_count || 0} members</Text>
                  </View>
                  <Text style={styles.advisor}>Advisor: {club.advisor_name}</Text>
                </View>
              </View>
            </View>
          ))}
        </View>
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
  clubsContainer: {
    padding: 15,
  },
  clubCard: {
    backgroundColor: "#fff",
    borderRadius: 12,
    marginBottom: 15,
    overflow: "hidden",
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 3,
  },
  clubImage: {
    width: "100%",
    height: 150,
    backgroundColor: "#f0f0f0",
  },
  clubInfo: {
    padding: 15,
  },
  clubName: {
    fontSize: 16,
    fontWeight: "600",
    color: "#333",
    marginBottom: 8,
  },
  clubDesc: {
    fontSize: 13,
    color: "#666",
    marginBottom: 12,
  },
  clubFooter: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },
  memberBadge: {
    flexDirection: "row",
    alignItems: "center",
  },
  memberCount: {
    fontSize: 12,
    color: "#0066cc",
    marginLeft: 5,
    fontWeight: "500",
  },
  advisor: {
    fontSize: 12,
    color: "#999",
  },
  emptyContainer: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
    paddingTop: 100,
  },
  emptyText: {
    fontSize: 16,
    color: "#999",
    marginTop: 12,
  },
})
