"use client"

import { useState, useEffect } from "react"
import { View, Text, FlatList, StyleSheet, ActivityIndicator, RefreshControl, Alert } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"

const API_URL = "https://schoolweb.ct.ws"

export default function Exams() {
  const [exams, setExams] = useState([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  useEffect(() => {
    fetchExams()
  }, [])

  const fetchExams = async () => {
    try {
      const userId = await SecureStore.getItemAsync("userId")
      const token = await SecureStore.getItemAsync("userToken")

      const response = await axios.get(`${API_URL}/api/admin/exams.php?user_id=${userId}`, {
        headers: { Authorization: `Bearer ${token}` },
      })

      if (response.data.success) {
        setExams(response.data.exams || [])
      }
    } catch (error) {
      console.error("Error fetching exams:", error)
      Alert.alert("Error", "Failed to load exams")
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  const onRefresh = () => {
    setRefreshing(true)
    fetchExams()
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#0066cc" />
      </View>
    )
  }

  return (
    <FlatList
      data={exams}
      keyExtractor={(item) => item.id.toString()}
      style={styles.container}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      renderItem={({ item }) => (
        <View style={styles.examCard}>
          <View style={styles.examHeader}>
            <Text style={styles.examName}>{item.exam_name}</Text>
            <Text style={styles.examDate}>{item.exam_date}</Text>
          </View>
          <View style={styles.examBody}>
            <Text style={styles.detail}>Grade: {item.grade}</Text>
            <Text style={styles.detail}>Students: {item.students_count || 0}</Text>
            <Text style={styles.detail}>Status: {item.status}</Text>
          </View>
        </View>
      )}
      ListEmptyComponent={
        <View style={styles.emptyContainer}>
          <Text style={styles.emptyText}>No exams found</Text>
        </View>
      }
    />
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
  examCard: {
    backgroundColor: "#fff",
    marginHorizontal: 15,
    marginVertical: 10,
    borderRadius: 8,
    overflow: "hidden",
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 2,
  },
  examHeader: {
    backgroundColor: "#0066cc",
    paddingHorizontal: 15,
    paddingVertical: 12,
    flexDirection: "row",
    justifyContent: "space-between",
  },
  examName: {
    fontSize: 16,
    fontWeight: "600",
    color: "#fff",
    flex: 1,
  },
  examDate: {
    fontSize: 12,
    color: "#e0e0e0",
  },
  examBody: {
    padding: 15,
  },
  detail: {
    fontSize: 13,
    color: "#666",
    marginBottom: 8,
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
  },
})
