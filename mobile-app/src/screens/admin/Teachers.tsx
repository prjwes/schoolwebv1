"use client"

import { useState, useEffect } from "react"
import { View, Text, FlatList, StyleSheet, ActivityIndicator, RefreshControl, Alert, TextInput } from "react-native"
import * as SecureStore from "expo-secure-store"
import axios from "axios"
import MaterialIcons from "react-native-vector-icons/MaterialIcons"

const API_URL = "https://schoolweb.ct.ws"

export default function Teachers() {
  const [teachers, setTeachers] = useState([])
  const [filteredTeachers, setFilteredTeachers] = useState([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [searchQuery, setSearchQuery] = useState("")

  useEffect(() => {
    fetchTeachers()
  }, [])

  useEffect(() => {
    filterTeachers()
  }, [searchQuery, teachers])

  const fetchTeachers = async () => {
    try {
      const userId = await SecureStore.getItemAsync("userId")
      const token = await SecureStore.getItemAsync("userToken")

      const response = await axios.get(`${API_URL}/api/admin/teachers.php?user_id=${userId}`, {
        headers: { Authorization: `Bearer ${token}` },
      })

      if (response.data.success) {
        setTeachers(response.data.teachers || [])
      }
    } catch (error) {
      console.error("Error fetching teachers:", error)
      Alert.alert("Error", "Failed to load teachers")
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }

  const filterTeachers = () => {
    if (!searchQuery) {
      setFilteredTeachers(teachers)
      return
    }

    const query = searchQuery.toLowerCase()
    const filtered = teachers.filter((teacher) => teacher.full_name.toLowerCase().includes(query))
    setFilteredTeachers(filtered)
  }

  const onRefresh = () => {
    setRefreshing(true)
    fetchTeachers()
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#0066cc" />
      </View>
    )
  }

  return (
    <View style={styles.container}>
      <View style={styles.searchContainer}>
        <MaterialIcons name="search" size={20} color="#999" style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          placeholder="Search teachers..."
          placeholderTextColor="#999"
          value={searchQuery}
          onChangeText={setSearchQuery}
        />
      </View>

      <FlatList
        data={filteredTeachers}
        keyExtractor={(item) => item.id.toString()}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        renderItem={({ item }) => (
          <View style={styles.teacherCard}>
            <View style={styles.teacherInfo}>
              <Text style={styles.teacherName}>{item.full_name}</Text>
              <Text style={styles.teacherDetail}>Email: {item.email}</Text>
              <Text style={styles.teacherDetail}>Subjects: {item.subjects_count || 0}</Text>
            </View>
          </View>
        )}
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyText}>No teachers found</Text>
          </View>
        }
      />
    </View>
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
  searchContainer: {
    backgroundColor: "#fff",
    flexDirection: "row",
    alignItems: "center",
    marginHorizontal: 15,
    marginVertical: 10,
    borderRadius: 8,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: "#ddd",
  },
  searchIcon: {
    marginRight: 10,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 10,
    fontSize: 14,
    color: "#333",
  },
  teacherCard: {
    backgroundColor: "#fff",
    marginHorizontal: 15,
    marginVertical: 8,
    borderRadius: 8,
    padding: 15,
    shadowColor: "#000",
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 2,
  },
  teacherInfo: {
    flex: 1,
  },
  teacherName: {
    fontSize: 16,
    fontWeight: "600",
    color: "#333",
    marginBottom: 8,
  },
  teacherDetail: {
    fontSize: 13,
    color: "#666",
    marginBottom: 4,
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
